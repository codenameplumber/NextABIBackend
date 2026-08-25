<?php

namespace App\Engine;

use App\Engine\Detectors;
use App\Models\Insight;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Layer 4 — Insights.
 *
 * Detectors emit raw signals: noisy, overlapping, unranked. This layer does the
 * editorial work — score, dedupe, rank, persist — and then stops. The cap
 * matters: a feed showing forty things contains no insights, it contains a
 * table.
 */
class InsightEngine
{
    /** Every detector, in no particular order. Scoring handles priority. */
    public const DETECTORS = [
        Detectors\StaleLeads::class,
        Detectors\HotUnworked::class,
        Detectors\SourceQuality::class,
        Detectors\CampaignBurn::class,
        Detectors\DemandGap::class,
        Detectors\StaleInventory::class,
        Detectors\AgentDivergence::class,
        Detectors\FunnelErosion::class,
        Detectors\RevenueTrend::class,
        Detectors\RevenueConcentration::class,
        Detectors\PaymentRisk::class,
        Detectors\CollectionHealth::class,
        Detectors\FeedbackCluster::class,
        Detectors\CustomerRisk::class,
    ];

    /**
     * Who cares about what. The role switcher re-ranks, it does not filter —
     * a finance risk does not stop existing when a sales manager logs in.
     */
    public const ROLE_WEIGHTS = [
        'ceo' => ['sales' => 1.0, 'finance' => 1.0, 'leads' => 0.85, 'properties' => 0.85,
            'marketing' => 0.8, 'customers' => 0.8, 'feedback' => 0.7],
        'sales' => ['sales' => 1.0, 'leads' => 1.0, 'properties' => 0.8, 'marketing' => 0.7,
            'customers' => 0.6, 'finance' => 0.45, 'feedback' => 0.5],
        'finance' => ['finance' => 1.0, 'sales' => 0.8, 'customers' => 0.6, 'properties' => 0.55,
            'leads' => 0.35, 'marketing' => 0.5, 'feedback' => 0.3],
        'agent' => ['leads' => 1.0, 'properties' => 0.8, 'customers' => 0.6, 'feedback' => 0.5,
            'sales' => 0.45, 'marketing' => 0.25, 'finance' => 0.2],
    ];

    public const AREAS = [
        'sales' => 'Sales', 'leads' => 'Leads', 'properties' => 'Properties',
        'marketing' => 'Marketing', 'customers' => 'Customers',
        'finance' => 'Finance', 'feedback' => 'Feedback',
    ];

    public const ROLES = [
        'ceo' => ['label' => 'CEO / Managing Director', 'hint' => 'Everything, weighted by revenue impact'],
        'sales' => ['label' => 'Sales Manager', 'hint' => 'Pipeline, agents and conversion'],
        'finance' => ['label' => 'Finance Lead', 'hint' => 'Collections, receivables and risk'],
        'agent' => ['label' => 'Sales Agent', 'hint' => 'My leads and the properties they want'],
    ];

    public function __construct(private Tenant $tenant) {}

    public static function for(Tenant $tenant): self
    {
        return new self($tenant);
    }

    /**
     * Run detectors and reconcile the results into the insights table.
     *
     * @param  array|null  $only  detector keys to re-run; null means all
     */
    public function run(?array $only = null): int
    {
        $metrics = Metrics::for($this->tenant);
        $metrics->flush();

        $signals = [];
        $ran = [];

        foreach (self::DETECTORS as $class) {
            /** @var Detector $detector */
            $detector = new $class;

            if ($only !== null && ! in_array($detector->key(), $only, true)) {
                continue;
            }

            $ran[] = $detector->key();

            try {
                foreach ($detector->run($metrics, $this->tenant) as $signal) {
                    $signals[] = $signal;
                }
            } catch (\Throwable $e) {
                // One broken detector must never take the feed down with it.
                Log::error('[detector:'.$detector->key().'] threw', [
                    'tenant' => $this->tenant->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->reconcile($signals, $ran, $metrics);
        $this->tenant->forceFill(['detectors_ran_at' => now()])->save();

        return count($signals);
    }

    /**
     * Upsert what was found, and retire what was not.
     *
     * Anything a detector stopped emitting is stamped resolved rather than
     * deleted, which gives the product a history of what was wrong and how
     * long it took to fix.
     */
    private function reconcile(array $signals, array $ranKeys, Metrics $metrics): void
    {
        $playbooks = app(PlaybookRegistry::class);

        DB::transaction(function () use ($signals, $ranKeys, $metrics, $playbooks) {
            $seen = [];

            foreach ($signals as $signal) {
                // Built once and reused for both scoring and persistence.
                // Recomputing inside priority() meant every playbook ran twice
                // per signal, each time re-querying the metrics layer.
                $recs = $playbooks->build($signal, $this->tenant, $metrics);
                $fingerprint = $signal->fingerprint($this->tenant);
                $seen[] = $fingerprint;

                $existing = Insight::withoutTenantScope()
                    ->where('tenant_id', $this->tenant->id)
                    ->where('fingerprint', $fingerprint)
                    ->first();

                $priority = $this->priority($signal, $recs);

                $attributes = [
                    'tenant_id' => $this->tenant->id,
                    'fingerprint' => $fingerprint,
                    'detector' => $signal->detector,
                    'archetype' => $signal->archetype,
                    'area' => $signal->area,
                    'title' => $signal->title,
                    'facts' => $signal->facts,
                    'evidence' => $signal->evidence,
                    'severity' => $signal->severity,
                    'confidence' => $signal->confidence,
                    'priority' => $priority,
                    'band' => $signal->band(),
                    'last_seen_at' => now(),
                    'resolved_at' => null,
                ];

                if ($existing) {
                    // The narrative is cached against the facts that produced
                    // it. If the numbers moved, the sentence is stale.
                    $factsHash = hash('sha256', json_encode($signal->facts));
                    if ($existing->narrative_hash !== $factsHash) {
                        $attributes['narrative'] = null;
                        $attributes['narrative_hash'] = null;
                    }

                    $existing->forceFill($attributes)->save();
                    $insight = $existing;
                } else {
                    $insight = Insight::create($attributes + [
                        'first_seen_at' => now(),
                        'status' => 'new',
                    ]);
                }

                $playbooks->sync($insight, $signal, $this->tenant, $recs);
            }

            // Retire findings whose detector ran and no longer reports them.
            $query = Insight::withoutTenantScope()
                ->where('tenant_id', $this->tenant->id)
                ->whereNull('resolved_at')
                ->whereIn('detector', $ranKeys);

            if ($seen) {
                $query->whereNotIn('fingerprint', $seen);
            }

            $query->update(['resolved_at' => now()]);
        });
    }

    /**
     * Priority score.
     *
     * Deliberately multiplicative: a high-severity finding you are not
     * confident about should not outrank a certain, moderate one. Money impact
     * is log-scaled so a huge naira figure does not drown a structural problem.
     */
    private function priority(Signal $signal, array $recs, ?string $role = null): float
    {
        $role ??= 'ceo';
        $roleWeight = self::ROLE_WEIGHTS[$role][$signal->area] ?? 0.5;

        $impact = 0.0;
        foreach ($recs as $rec) {
            $impact = max($impact, (float) ($rec['impact'] ?? 0));
        }

        $impactWeight = $impact > 0 ? min(1.0, log10($impact) / 10) : 0.28;

        return $signal->severity * $signal->confidence * $roleWeight * (0.45 + $impactWeight * 0.55);
    }

    /* --------------------------------------------------------------- feed */

    /**
     * The ranked feed for a role.
     *
     * Capped at eight, and at most three from any one area — otherwise a noisy
     * detector monopolises the whole screen.
     */
    public function feed(string $role = 'ceo', int $limit = 8, ?string $area = null, ?string $band = null): array
    {
        $insights = $this->live($area, $band);

        $scored = collect($insights)->map(function (Insight $i) use ($role) {
            $weight = self::ROLE_WEIGHTS[$role][$i->area] ?? 0.5;
            $baseWeight = self::ROLE_WEIGHTS['ceo'][$i->area] ?? 0.5;
            // Re-weight the stored priority for this role rather than
            // recomputing it — the expensive part is the detector run.
            $i->setAttribute('role_priority', $baseWeight > 0
                ? $i->priority * ($weight / $baseWeight)
                : $i->priority);

            return $i;
        })->sortByDesc('role_priority')->values();

        $perArea = [];
        $out = [];

        foreach ($scored as $insight) {
            $perArea[$insight->area] = ($perArea[$insight->area] ?? 0) + 1;
            if ($perArea[$insight->area] > 3) {
                continue;
            }
            $out[] = $insight;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @return Insight[] */
    public function live(?string $area = null, ?string $band = null): array
    {
        return Insight::query()
            ->with('recommendations')
            ->whereNull('resolved_at')
            ->whereNull('dismissed_at')
            ->when($area, fn ($q) => $q->where('area', $area))
            ->when($band, fn ($q) => $q->where('band', $band))
            ->orderByDesc('priority')
            ->get()
            ->all();
    }

    public function counts(): array
    {
        $live = $this->live();

        $byArea = [];
        foreach (array_keys(self::AREAS) as $key) {
            $byArea[$key] = count(array_filter($live, fn ($i) => $i->area === $key));
        }

        return [
            'total' => count($live),
            'critical' => count(array_filter($live, fn ($i) => $i->band === 'critical')),
            'high' => count(array_filter($live, fn ($i) => $i->band === 'high')),
            'new' => count(array_filter($live, fn ($i) => $i->status === 'new')),
            'byArea' => $byArea,
        ];
    }

    /** Sum of the best estimated impact per finding. */
    public function impactOnTable(array $insights): float
    {
        $total = 0.0;
        foreach ($insights as $i) {
            $total += (float) $i->recommendations->max('impact');
        }

        return $total;
    }
}
