<?php

namespace App\Engine;

use App\Models\Agent;
use App\Models\Insight;
use App\Models\Recommendation;
use App\Models\Tenant;

/**
 * Layer 5 — Playbooks.
 *
 * The step most BI tools never take: turning a finding into something a human
 * can press. Each playbook maps a Signal to Recommendations, and every
 * Recommendation carries an executable action descriptor, not a suggestion
 * string.
 */
class PlaybookRegistry
{
    /** Persist the recommendations for an insight, preserving what was done. */
    public function sync(Insight $insight, Signal $signal, Tenant $tenant, array $recs): void
    {
        $keys = [];

        foreach ($recs as $rec) {
            $key = $signal->fingerprint($tenant).':'.$rec['key'];
            $keys[] = $key;

            Recommendation::withoutTenantScope()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => $key],
                [
                    'insight_id' => $insight->id,
                    'label' => $rec['label'],
                    'rationale' => $rec['rationale'] ?? null,
                    'effort' => $rec['effort'] ?? 'medium',
                    'impact' => $rec['impact'] ?? 0,
                    'is_primary' => $rec['primary'] ?? false,
                    'action_type' => $rec['action'],
                    'payload' => $rec['payload'] ?? [],
                ],
            );
        }

        // Drop recommendations this playbook no longer produces, but never one
        // already carried out — the action log has to stay honest.
        Recommendation::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('insight_id', $insight->id)
            ->whereNotIn('key', $keys)
            ->whereNull('done_at')
            ->delete();
    }

    /**
     * Agents with the most headroom — used by every redistribution action.
     *
     * Takes the caller's Metrics instance rather than building its own: a fresh
     * instance has an empty request-memo, so constructing one per signal turned
     * a single detector pass into dozens of repeated aggregate queries.
     */
    private function availableAgents(Metrics $metrics, int $n = 4): array
    {
        $a = $metrics->agents();

        $pool = array_values(array_filter(
            $a['rows'],
            fn ($x) => $x['active'] && $x['closeRate'] >= $a['baselineCloseRate'] * 0.9,
        ));

        usort($pool, fn ($x, $y) => $x['openLeads'] <=> $y['openLeads']);

        return array_slice($pool, 0, $n);
    }

    private static function firstNames(array $agents): string
    {
        return implode(', ', array_map(fn ($a) => explode(' ', $a['name'])[0], $agents));
    }

    private static function money(float $n): string
    {
        $abs = abs($n);

        return match (true) {
            $abs >= 1e9 => '₦'.number_format($n / 1e9, 1).'B',
            $abs >= 1e6 => '₦'.number_format($n / 1e6, 1).'M',
            $abs >= 1e3 => '₦'.number_format($n / 1e3).'K',
            default => '₦'.number_format($n),
        };
    }

    private static function pct(float $n, int $dp = 0): string
    {
        return number_format($n * 100, $dp).'%';
    }

    /** @return array<int, array> */
    public function build(Signal $s, Tenant $tenant, Metrics $metrics): array
    {
        $f = $s->facts;

        return match ($s->detector) {

            'staleLeads' => (function () use ($f, $metrics) {
                $helpers = $this->availableAgents($metrics);

                return array_values(array_filter([
                    [
                        'key' => 'sweep', 'primary' => true, 'effort' => 'low',
                        'label' => 'Schedule follow-up for all '.$f['count'].' stale '
                            .($f['count'] === 1 ? 'lead' : 'leads'),
                        'rationale' => 'Resets contact clocks and puts '.self::money($f['pipeline'])
                            .' of pipeline back in play.',
                        'impact' => $f['estLoss'],
                        'action' => 'SCHEDULE_FOLLOWUP',
                        'payload' => ['filter' => 'stale', 'note' => 'Bulk re-engagement sweep'],
                    ],
                    $helpers ? [
                        'key' => 'reassign', 'effort' => 'low',
                        'label' => 'Reassign the highest-scoring to agents with capacity',
                        'rationale' => self::firstNames($helpers)
                            .' are below average workload and above average close rate.',
                        'impact' => $f['pipeline'] * 0.12,
                        'action' => 'ASSIGN_LEADS',
                        'payload' => ['filter' => 'stale', 'minScore' => 65,
                            'agentIds' => array_column($helpers, 'id')],
                    ] : null,
                    [
                        'key' => 'sla', 'effort' => 'medium',
                        'label' => 'Set a 7-day contact SLA with automatic escalation',
                        'rationale' => 'Prevents the backlog rebuilding once it has been cleared.',
                        'impact' => $f['pipeline'] * 0.05,
                        'action' => 'SET_POLICY',
                        'payload' => ['key' => 'leadContactSlaDays', 'value' => 7],
                    ],
                ]));
            })(),

            'hotUnworked' => [[
                'key' => 'contact', 'primary' => true, 'effort' => 'low',
                'label' => 'Contact all '.$f['count'].' today',
                'rationale' => 'Average score '.$f['avgScore'].', '.$f['avgDays']
                    .' days since last touch, '.self::money($f['value']).' combined budget.',
                'impact' => $f['value'] * 0.2,
                'action' => 'SCHEDULE_FOLLOWUP',
                'payload' => ['filter' => 'hot-idle', 'note' => 'Priority same-day contact'],
            ]],

            'sourceQuality' => [
                [
                    'key' => 'shift', 'primary' => true, 'effort' => 'medium',
                    'label' => 'Shift acquisition budget from '.$f['source'].' to '.$f['bestSource'],
                    'rationale' => $f['bestSource'].' converts at '.self::pct($f['bestConversion'], 1)
                        .' versus '.self::pct($f['conversion'], 1).' - '
                        .number_format($f['multiple'], 1).'x better.',
                    'impact' => 0,
                    'action' => 'NAVIGATE', 'payload' => ['route' => 'marketing'],
                ],
                [
                    'key' => 'qualify', 'effort' => 'medium',
                    'label' => 'Add a qualification step before '.$f['source'].' leads reach agents',
                    'rationale' => 'Agents are spending capacity on '.number_format($f['leads'])
                        .' leads that close '.number_format($f['multiple'], 1).'x less often.',
                    'impact' => 0,
                    'action' => 'SET_POLICY',
                    'payload' => ['key' => 'qualifyBeforeAssign:'.$f['source'], 'value' => true],
                ],
            ],

            'campaignBurn' => (function () use ($f, $metrics) {
                $mk = $metrics->marketing();
                $best = null;
                foreach ($mk['campaigns'] as $c) {
                    if ($c['leads'] >= 12 && $c['id'] !== $f['campaignId']
                        && (! $best || $c['roas'] > $best['roas'])) {
                        $best = $c;
                    }
                }

                return array_values(array_filter([
                    [
                        'key' => 'pause', 'primary' => true, 'effort' => 'low',
                        'label' => 'Pause "'.$f['name'].'"',
                        'rationale' => self::money($f['wasted']).' of the '.self::money($f['spend'])
                            .' spent is not returning leads that close.',
                        'impact' => $f['wasted'],
                        'action' => 'PAUSE_CAMPAIGN', 'payload' => ['campaignId' => $f['campaignId']],
                    ],
                    $best ? [
                        'key' => 'reallocate', 'effort' => 'low',
                        'label' => 'Move remaining budget to "'.$best['name'].'"',
                        'rationale' => 'That campaign is returning '.number_format($best['roas'], 1)
                            .'x on spend versus '.number_format($f['roas'], 2).'x here.',
                        'impact' => $f['spend'] * max(0, $best['roas'] - $f['roas']) * 0.3,
                        'action' => 'REALLOCATE_BUDGET',
                        'payload' => ['from' => $f['campaignId'], 'to' => $best['id'],
                            'amount' => round($f['spend'] * 0.4)],
                    ] : null,
                    [
                        'key' => 'requalify', 'effort' => 'medium',
                        'label' => 'Re-score the '.number_format($f['leads']).' leads this campaign produced',
                        'rationale' => 'Separates genuinely poor traffic from leads that were never worked.',
                        'impact' => 0,
                        'action' => 'RESCORE_LEADS', 'payload' => ['campaignId' => $f['campaignId']],
                    ],
                ]));
            })(),

            'demandGap' => [
                [
                    'key' => 'reprice', 'primary' => true, 'effort' => 'low',
                    'label' => 'Raise asking price 6% on the '.$f['available'].' remaining '
                        .($f['available'] === 1 ? 'unit' : 'units'),
                    'rationale' => round($f['demandIndex']).' inquiries per available unit against a '
                        .'portfolio norm of '.number_format($f['baseline'], 1)
                        .' - the segment is underpriced against demand.',
                    'impact' => $f['upside'] * $f['available'],
                    'action' => 'ADJUST_PRICE',
                    'payload' => ['location' => $f['location'], 'type' => $f['type'],
                        'bedrooms' => $f['bedrooms'], 'pctChange' => 0.06],
                ],
                [
                    'key' => 'source', 'effort' => 'high',
                    'label' => 'Flag '.$f['location'].' '.$f['type'].' for acquisition',
                    'rationale' => 'Stock will clear in roughly '.max(1, round($f['avgDom'] / 4))
                        .' weeks at the current inquiry rate.',
                    'impact' => $f['avgPrice'] * 0.04 * 6,
                    'action' => 'FLAG_ACQUISITION',
                    'payload' => ['location' => $f['location'], 'type' => $f['type'],
                        'bedrooms' => $f['bedrooms']],
                ],
                [
                    'key' => 'match', 'effort' => 'low',
                    'label' => 'Match waiting leads to the remaining units',
                    'rationale' => 'Open leads targeting this exact segment can be routed before stock clears.',
                    'impact' => $f['avgPrice'] * 0.5,
                    'action' => 'MATCH_LEADS',
                    'payload' => ['location' => $f['location'], 'type' => $f['type'],
                        'bedrooms' => $f['bedrooms']],
                ],
            ],

            'staleInventory' => [
                [
                    'key' => 'discount', 'primary' => true, 'effort' => 'low',
                    'label' => 'Apply a 4% price correction to the '.$f['count'].' slowest '
                        .($f['count'] === 1 ? 'listing' : 'listings'),
                    'rationale' => self::money($f['value']).' of capital is tied up, costing roughly '
                        .self::money($f['carryCost']).' a month to carry.',
                    'impact' => $f['carryCost'] * 6,
                    'action' => 'ADJUST_PRICE', 'payload' => ['filter' => 'stale', 'pctChange' => -0.04],
                ],
                [
                    'key' => 'promote', 'effort' => 'medium',
                    'label' => 'Feature them in the next campaign refresh',
                    'rationale' => 'Long-listed stock rarely moves without new exposure.',
                    'impact' => 0,
                    'action' => 'FLAG_PROMOTION', 'payload' => ['filter' => 'stale'],
                ],
            ],

            'agentDivergence' => (function () use ($f, $metrics) {
                $helpers = $this->availableAgents($metrics, 3);

                return array_values(array_filter([
                    $helpers ? [
                        'key' => 'rebalance', 'primary' => true, 'effort' => 'medium',
                        'label' => 'Rebalance '.round($f['lagLeads'] * 0.35).' leads from the lagging cohort',
                        'rationale' => "At the top cohort's ".self::pct($f['leadRate'])
                            .' close rate that pipeline is worth '.self::money($f['recoverable']).' more.',
                        'impact' => $f['recoverable'],
                        'action' => 'REBALANCE_LEADS',
                        'payload' => ['fromAgentIds' => $f['laggardIds'],
                            'toAgentIds' => array_column($helpers, 'id'), 'share' => 0.35],
                    ] : null,
                    [
                        'key' => 'coach', 'effort' => 'high',
                        'label' => 'Pair '.$f['laggards'].' '
                            .($f['laggards'] === 1 ? 'agent' : 'agents').' with top performers for 4 weeks',
                        'rationale' => implode(' and ', array_slice($f['leaderNames'], 0, 2))
                            .' are closing at '.self::pct($f['leadRate'])
                            .'; the gap is '.self::pct($f['gap']).'.',
                        'impact' => $f['recoverable'] * 0.4,
                        'action' => 'ASSIGN_COACHING',
                        'payload' => ['agentIds' => $f['laggardIds'], 'mentorIds' => $f['leaderIds']],
                    ],
                ]));
            })(),

            'funnelErosion' => [
                [
                    'key' => 'review', 'primary' => true, 'effort' => 'medium',
                    'label' => 'Review site-visit follow-up within 48 hours',
                    'rationale' => number_format($f['dropped']).' leads are being lost at this step that '
                        .'the '.self::pct($f['early']).' baseline would have advanced.',
                    'impact' => 0,
                    'action' => 'SET_POLICY', 'payload' => ['key' => 'postVisitFollowUpHours', 'value' => 48],
                ],
                [
                    'key' => 'requalify', 'effort' => 'low',
                    'label' => 'Re-score all leads currently stuck at viewing',
                    'rationale' => 'Separates genuine intent from leads that should never have got there.',
                    'impact' => 0,
                    'action' => 'RESCORE_LEADS', 'payload' => ['status' => 'viewing'],
                ],
            ],

            'revenueTrend' => $f['direction'] === 'up'
                ? [[
                    'key' => 'sustain', 'primary' => true, 'effort' => 'low',
                    'label' => 'Lock the current channel mix for another quarter',
                    'rationale' => 'The trend is compounding - the risk is changing something that works.',
                    'impact' => 0, 'action' => 'ACKNOWLEDGE', 'payload' => [],
                ]]
                : [
                    [
                        'key' => 'pipeline', 'primary' => true, 'effort' => 'medium',
                        'label' => 'Audit top-of-funnel volume against the decline',
                        'rationale' => 'Revenue is down '.self::pct(abs($f['change'])).' across '
                            .$f['months'].' months - confirm whether it is volume or conversion.',
                        'impact' => 0, 'action' => 'NAVIGATE', 'payload' => ['route' => 'analytics'],
                    ],
                    [
                        'key' => 'reprice', 'effort' => 'medium',
                        'label' => 'Review pricing on slow-moving inventory',
                        'rationale' => 'Falling revenue with flat volume usually means deal size, not count.',
                        'impact' => 0, 'action' => 'NAVIGATE',
                        'payload' => ['route' => 'properties', 'params' => ['filter' => 'stale']],
                    ],
                ],

            'revenueConcentration' => [[
                'key' => 'spread', 'primary' => true, 'effort' => 'medium',
                'label' => 'Distribute new high-value leads beyond the top 3',
                'rationale' => self::pct($f['concentration']).' of revenue on three people is a '
                    .'continuity risk, not a performance win.',
                'impact' => 0,
                'action' => 'SET_POLICY', 'payload' => ['key' => 'leadDistributionCapPerAgent', 'value' => 0.2],
            ]],

            'paymentRisk' => array_values(array_filter([
                [
                    'key' => 'remind', 'primary' => true, 'effort' => 'low',
                    'label' => 'Issue payment reminders on all '.$f['instalments'].' overdue '
                        .($f['instalments'] === 1 ? 'instalment' : 'instalments'),
                    'rationale' => 'Recovers up to '.self::money($f['amount'] * 0.45).' without escalation.',
                    'impact' => $f['amount'] * 0.45,
                    'action' => 'SEND_REMINDERS', 'payload' => ['filter' => 'overdue'],
                ],
                $f['vipCount'] ? [
                    'key' => 'escalate', 'effort' => 'medium',
                    'label' => 'Escalate '.$f['vipCount'].' high-value '
                        .($f['vipCount'] === 1 ? 'account' : 'accounts').' to relationship management',
                    'rationale' => $f['topName'].' is '.$f['topDays'].' days late on '
                        .self::money($f['topAmount']).' - too large for an automated chase.',
                    'impact' => $f['amount'] * 0.6,
                    'action' => 'ESCALATE_ACCOUNTS', 'payload' => ['customerIds' => $f['vipIds']],
                ] : null,
                [
                    'key' => 'terms', 'effort' => 'medium',
                    'label' => 'Offer restructured instalment terms on 90+ day balances',
                    'rationale' => self::money($f['over90']).' is aged beyond 90 days and unlikely to '
                        .'clear on the current schedule.',
                    'impact' => $f['over90'] * 0.5,
                    'action' => 'RESTRUCTURE_TERMS', 'payload' => ['minOverdueDays' => 90],
                ],
            ])),

            'collectionHealth' => [[
                'key' => 'chase', 'primary' => true, 'effort' => 'low',
                'label' => 'Run a collections sweep on everything past due',
                'rationale' => 'Ratio is '.self::pct($f['ratio']).' against a 92% target, with '
                    .self::money($f['outstanding']).' outstanding.',
                'impact' => $f['outstanding'] * 0.3,
                'action' => 'SEND_REMINDERS', 'payload' => ['filter' => 'all-due'],
            ]],

            'feedbackCluster' => [
                [
                    'key' => 'sla', 'primary' => true, 'effort' => 'medium',
                    'label' => 'Introduce a 24-hour first-response SLA',
                    'rationale' => '"'.$f['tag'].'" accounts for '.$f['count'].' of the last 60 '
                        ."days' complaints, and ".$f['negative'].' of those were 1-2 star.',
                    'impact' => 0,
                    'action' => 'SET_POLICY', 'payload' => ['key' => 'firstResponseSlaHours', 'value' => 24],
                ],
                [
                    'key' => 'recover', 'effort' => 'low',
                    'label' => 'Open service-recovery outreach to the '.$f['negative'].' unhappy '
                        .($f['negative'] === 1 ? 'customer' : 'customers'),
                    'rationale' => 'Recovered detractors refer at roughly the rate of neutral customers.',
                    'impact' => 0,
                    'action' => 'OUTREACH', 'payload' => ['tag' => $f['tag'], 'maxRating' => 2],
                ],
                [
                    'key' => 'coach', 'effort' => 'low',
                    'label' => 'Add response time to weekly agent scorecards',
                    'rationale' => 'Makes the metric visible to the people who move it.',
                    'impact' => 0,
                    'action' => 'SET_POLICY', 'payload' => ['key' => 'scorecardResponseTime', 'value' => true],
                ],
            ],

            'customerRisk' => [[
                'key' => 'outreach', 'primary' => true, 'effort' => 'medium',
                'label' => 'Open recovery conversations with '.$f['count'].' '
                    .($f['count'] === 1 ? 'detractor' : 'detractors'),
                'rationale' => 'They represent '.self::money($f['value']).' in closed revenue and are '
                    .'your most likely source of negative referral.',
                'impact' => $f['value'] * 0.1,
                'action' => 'OUTREACH', 'payload' => ['maxRating' => 2.5],
            ]],

            default => [],
        };
    }
}
