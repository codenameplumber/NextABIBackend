<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Feedback;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Property;
use App\Engine\Metrics;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The Time Machine.
 *
 * Advancing has to advance the *business*, not just the date. On a frozen
 * dataset a jump forward makes revenue fall off a cliff and every trend
 * collapse — which reads as a bug and quietly destroys the one claim the
 * control exists to demonstrate.
 *
 * The prototype could move a simulated clock. Here the clock is real, so the
 * equivalent is the mirror image: age every existing row by N days, then
 * simulate the N days that have now "elapsed" between then and now. The
 * business ends up N days further along and `now` is still now.
 *
 * What gets simulated is derived from the data rather than hardcoded — the
 * lead arrival rate, the win rate and each agent's skill all come out of the
 * existing rows, so the simulation stays consistent with whatever the business
 * already looks like, including after a CSV import.
 *
 * Deterministic: seeded on (tenant, days, current lead count), so the same
 * jump from the same state always produces the same outcome.
 */
class TimeMachine
{
    private const DAY = 86400;

    private const STAGES = ['new', 'contacted', 'qualified', 'viewing', 'negotiating', 'won'];

    /** Every column that represents a moment in this business's history. */
    private const CLOCKED = [
        'leads' => ['created_at', 'last_contact_at', 'converted_at', 'follow_up_at', 'reassigned_at', 'rescored_at'],
        'properties' => ['listed_at', 'sold_at', 'repriced_at', 'created_at'],
        'deals' => ['opened_at', 'closed_at', 'created_at'],
        'customers' => ['since', 'escalated_at', 'recovery_opened_at', 'created_at'],
        'payments' => ['due_at', 'paid_at', 'reminded_at', 'original_due_at', 'created_at'],
        'feedbacks' => ['created_at'],
        'activities' => ['at', 'created_at'],
        'campaigns' => ['start_at', 'end_at', 'paused_at', 'created_at'],
        'agents' => ['joined_at'],
        'lead_stage_transitions' => ['at'],
    ];

    private Rng $r;

    public function __construct(private Tenant $tenant) {}

    public function advance(int $days): array
    {
        $days = max(1, min(180, $days));

        $this->r = new Rng(crc32($this->tenant->id.':'.$days.':'.Lead::count()));

        // Whatever happens to the transaction, the cached figures describe the
        // world before the jump and have to go.
        $summary = DB::transaction(function () use ($days) {
            $this->ageEverything($days);

            $summary = ['days' => $days, 'newLeads' => 0, 'advanced' => 0, 'lost' => 0,
                'won' => 0, 'revenue' => 0.0, 'paymentsCleared' => 0, 'collected' => 0.0];

            $from = now()->copy()->subDays($days);

            $summary = $this->arriveNewLeads($summary, $from, $days);
            $summary = $this->progressPipeline($summary, $from, $days);
            $summary = $this->settleInstalments($summary);

            $this->rerankCustomers();

            return $summary;
        });

        Metrics::for($this->tenant)->flush();

        return $summary;
    }

    /* ----------------------------------------------------------- ageing */

    /**
     * Move every timestamp back.
     *
     * Done in SQL rather than in PHP because the alternative is loading two
     * thousand models to subtract a number from each of them.
     */
    private function ageEverything(int $days): void
    {
        foreach (self::CLOCKED as $table => $columns) {
            $sets = [];

            foreach ($columns as $c) {
                if (! \Illuminate\Support\Facades\Schema::hasColumn($table, $c)) {
                    continue;
                }
                $sets[] = "`{$c}` = DATE_SUB(`{$c}`, INTERVAL {$days} DAY)";
            }

            if ($sets) {
                DB::statement(
                    "UPDATE `{$table}` SET ".implode(', ', $sets).' WHERE tenant_id = ?',
                    [$this->tenant->id],
                );
            }
        }

        // Days on market is a derived count, not a timestamp — it has to move
        // with the clock or the stale-inventory detector reads the old world.
        DB::statement(
            'UPDATE `properties` SET days_on_market = days_on_market + ?
             WHERE tenant_id = ? AND status = ?',
            [$days, $this->tenant->id, 'available'],
        );
    }

    /* ------------------------------------------------------- new leads */

    private function arriveNewLeads(array $summary, $from, int $days): array
    {
        $total = Lead::count();
        $perDay = $total / 610;                       // the seeded history window
        $expected = $perDay * $days * Vocab::seasonality(now()->timestamp);
        $count = max(0, (int) round($this->r->gauss($expected, $expected * 0.18)));

        if ($count === 0) {
            return $summary;
        }

        $agents = Agent::where('active', true)->pluck('id')->all() ?: Agent::pluck('id')->all();

        if (! $agents) {
            return $summary;
        }

        $rows = [];
        $stamp = now()->timestamp;

        for ($i = 0; $i < $count; $i++) {
            $src = $this->r->fromTable(array_map(fn ($s) => ['v' => $s, 'w' => $s['w']], Vocab::SOURCES));
            $loc = $this->r->fromTable(array_map(fn ($l) => ['v' => $l, 'w' => $l['w']], Vocab::LOCATIONS));
            $type = $this->r->fromTable(array_map(fn ($t) => ['v' => $t, 'w' => $t['w']], Vocab::PROP_TYPES));

            $created = $this->r->between($from->timestamp, now()->timestamp);

            // Freshly captured leads start shallow — they have not had time to
            // progress, and pretending otherwise inflates the top of the funnel.
            $depth = $this->r->weighted([0, 1, 2], [34, 44, 22]);

            $rows[] = [
                'tenant_id' => $this->tenant->id,
                'name' => $this->r->pick(Vocab::FIRST).' '.$this->r->pick(Vocab::LAST),
                'email' => "sim.{$stamp}.{$i}@example.com",
                'phone' => '+2348'.$this->r->int(10, 99).$this->r->int(1000000, 9999999),
                'source' => $src['name'],
                'channel' => $src['channel'],
                'status' => self::STAGES[$depth],
                'max_stage' => self::STAGES[$depth],
                'score' => (int) round(min(99, max(8, 30 + $src['quality'] * 18 + $depth * 7 + $this->r->gauss(0, 9)))),
                'budget' => round($loc['base'] * $type['mult'] * $this->r->float(0.7, 1.5) / 1e6) * 1e6,
                'wants_location' => $loc['name'],
                'wants_type' => $type['name'],
                'assigned_agent_id' => $agents[$i % count($agents)],
                'created_at' => date('Y-m-d H:i:s', $created),
                'updated_at' => now(),
                'last_contact_at' => date('Y-m-d H:i:s', min(now()->timestamp, $created + $this->r->int(0, 6) * self::DAY)),
                'is_open' => true,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Lead::insert($chunk);
        }

        $summary['newLeads'] = count($rows);

        return $summary;
    }

    /* -------------------------------------------------- open pipeline */

    private function progressPipeline(array $summary, $from, int $days): array
    {
        $skills = Agent::pluck('skill', 'id')->all();
        $available = Property::where('status', 'available')->get();
        $taken = [];

        $open = Lead::where('is_open', true)
            ->where('created_at', '<', $from)
            ->get();

        foreach ($open as $lead) {
            $skill = (float) ($skills[$lead->assigned_agent_id] ?? 0.6);
            $idle = $lead->last_contact_at
                ? $lead->last_contact_at->diffInDays($from, absolute: true) : 30;

            // Neglected leads decay; worked leads progress. This is precisely
            // what makes the follow-up action matter across a jump: clear the
            // stale backlog first and fewer of them go cold.
            $chanceLost = min(0.55, 0.03 * $days / 7 + $idle / 220);
            $chanceAdvance = max(0.04, (0.16 * $days / 7) * (0.5 + $skill) * ($idle > 21 ? 0.35 : 1));

            if ($this->r->chance($chanceLost)) {
                $lead->forceFill([
                    'status' => 'lost', 'is_open' => false,
                    'lost_reason' => $this->r->pick(Vocab::LOST_REASONS),
                ])->save();
                $summary['lost']++;

                continue;
            }

            if (! $this->r->chance($chanceAdvance)) {
                continue;
            }

            $cur = array_search($lead->max_stage ?: $lead->status, self::STAGES, true);
            $next = min(count(self::STAGES) - 1, ($cur === false ? 0 : $cur) + 1);
            $contactedAt = $this->r->between($from->timestamp, now()->timestamp);

            $lead->forceFill([
                'status' => self::STAGES[$next],
                'max_stage' => self::STAGES[$next],
                'last_contact_at' => date('Y-m-d H:i:s', $contactedAt),
            ])->save();

            $summary['advanced']++;

            Activity::create([
                'tenant_id' => $this->tenant->id,
                'type' => $this->r->pick(['call', 'whatsapp', 'site_visit', 'email']),
                'entity_type' => 'lead', 'entity_id' => $lead->id,
                'agent_id' => $lead->assigned_agent_id,
                'at' => date('Y-m-d H:i:s', $contactedAt),
            ]);

            if (self::STAGES[$next] !== 'won') {
                continue;
            }

            // Reaching the last stage is not the same as closing.
            if (! $this->r->chance(0.45 + $skill * 0.35)) {
                $lead->forceFill(['status' => 'negotiating', 'max_stage' => 'negotiating'])->save();

                continue;
            }

            $match = $available->first(fn ($p) => ! isset($taken[$p->id])
                && $p->location === $lead->wants_location
                && abs($p->price - $lead->budget) / max(1, $lead->budget) < 0.5)
                ?? $available->first(fn ($p) => ! isset($taken[$p->id])
                    && abs($p->price - $lead->budget) / max(1, $lead->budget) < 0.65);

            if (! $match) {
                $lead->forceFill(['status' => 'negotiating', 'max_stage' => 'negotiating'])->save();

                continue;
            }

            $taken[$match->id] = true;
            $summary = $this->closeDeal($summary, $lead, $match, $from);
        }

        return $summary;
    }

    private function closeDeal(array $summary, Lead $lead, Property $match, $from): array
    {
        $closedAt = $this->r->between($from->timestamp, now()->timestamp);
        $value = round($match->price * $this->r->float(0.94, 1.02) / 5e5) * 5e5;

        $match->forceFill([
            'status' => 'sold',
            'sold_at' => date('Y-m-d H:i:s', $closedAt),
            'days_on_market' => (int) round(($closedAt - $match->listed_at->timestamp) / self::DAY),
        ])->save();

        // The buyer is reachable, because SEND_REMINDERS mails them. A new
        // customer with no address is one the reminder sweep silently skips.
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => $lead->name, 'email' => $lead->email, 'phone' => $lead->phone,
            'agent_id' => $lead->assigned_agent_id,
            'since' => date('Y-m-d H:i:s', $closedAt),
            'ltv' => $value, 'segment' => 'standard', 'source' => $lead->source,
        ]);

        $deal = Deal::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id, 'property_id' => $match->id,
            'agent_id' => $lead->assigned_agent_id, 'customer_id' => $customer->id,
            'value' => $value,
            'commission' => round($value * $this->r->float(0.021, 0.052)),
            'opened_at' => $lead->created_at,
            'closed_at' => date('Y-m-d H:i:s', $closedAt),
            'cycle_days' => (int) round(($closedAt - $lead->created_at->timestamp) / self::DAY),
            'source' => $lead->source, 'campaign_id' => $lead->campaign_id,
            'location' => $match->location, 'property_type' => $match->type,
            'won' => true,
        ]);

        $lead->forceFill([
            'status' => 'won', 'is_open' => false,
            'converted_at' => date('Y-m-d H:i:s', $closedAt),
            'property_id' => $match->id,
        ])->save();

        // An instalment plan, so the new deal has receivables like any other.
        $plan = $this->r->weighted([1, 2, 3, 4, 6], [30, 22, 20, 16, 12]);
        $each = round($value / $plan / 1e4) * 1e4;
        $rows = [];

        for ($n = 0; $n < $plan; $n++) {
            $dueAt = $closedAt + $n * $this->r->int(28, 45) * self::DAY;
            $due = $dueAt <= now()->timestamp;
            $paid = $due && $this->r->chance(0.93);

            $rows[] = [
                'tenant_id' => $this->tenant->id,
                'deal_id' => $deal->id, 'customer_id' => $customer->id,
                'agent_id' => $deal->agent_id,
                'amount' => $n === $plan - 1 ? $value - $each * ($plan - 1) : $each,
                'instalment' => $n + 1, 'of_total' => $plan,
                'due_at' => date('Y-m-d H:i:s', $dueAt),
                'paid_at' => $paid ? date('Y-m-d H:i:s', $dueAt) : null,
                'status' => $paid ? 'paid' : ($due ? 'pending' : 'scheduled'),
                'method' => $this->r->pick(['Bank Transfer', 'Cheque', 'Card', 'Mortgage Drawdown']),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        Payment::insert($rows);

        $summary['won']++;
        $summary['revenue'] += $value;

        return $summary;
    }

    /* ------------------------------------------------- instalments due */

    private function settleInstalments(array $summary): array
    {
        $due = Payment::whereNull('paid_at')->where('due_at', '<=', now())->get();

        foreach ($due as $payment) {
            if (! $this->r->chance(0.9)) {
                continue;
            }

            $payment->forceFill([
                'status' => 'paid',
                'paid_at' => now()->copy()->subDays($this->r->int(0, 9))->max($payment->due_at),
            ])->save();

            $summary['paymentsCleared']++;
            $summary['collected'] += (float) $payment->amount;
        }

        return $summary;
    }

    /** Value ranks drive the segments, so they have to move with the money. */
    private function rerankCustomers(): void
    {
        $all = Customer::orderByDesc('ltv')->pluck('id')->all();
        $n = max(1, count($all));

        foreach ($all as $i => $id) {
            Customer::whereKey($id)->update([
                'rank' => $i + 1,
                'segment' => match (true) {
                    $i < $n * 0.08 => 'vip',
                    $i < $n * 0.30 => 'high_value',
                    $i < $n * 0.75 => 'standard',
                    default => 'entry',
                },
            ]);
        }
    }

    /** Human-readable summary for the toast. */
    public static function describe(array $s): string
    {
        $bits = array_filter([
            $s['newLeads'] ? $s['newLeads'].' new leads' : null,
            $s['won'] ? $s['won'].' deals closed' : null,
            $s['lost'] ? $s['lost'].' went cold' : null,
            $s['paymentsCleared'] ? $s['paymentsCleared'].' instalments paid' : null,
        ]);

        return $bits ? implode(' · ', $bits) : 'no material change';
    }
}
