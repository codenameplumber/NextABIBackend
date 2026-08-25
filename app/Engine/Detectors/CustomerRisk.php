<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

use App\Models\Customer;

/**
 * Customers sitting at detractor-level satisfaction, weighted by what they
 * have actually spent.
 */
class CustomerRisk implements Detector
{
    public function key(): string { return 'customerRisk'; }

    public function archetype(): string { return 'threshold'; }

    public function area(): string { return 'customers'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $c = $m->customers();

        if (! $c['detractors'] || ! $c['total']) {
            return [];
        }

        $share = Stats::safeDiv($c['detractors'], $c['total']);
        if ($share < 0.11) {
            return [];
        }

        $at = Customer::whereNotNull('satisfaction')
            ->where('satisfaction', '<=', 2.5)
            ->orderByDesc('ltv')->get();

        $value = (float) $at->sum('ltv');
        $top = $at->first();

        return [new Signal(
            detector: $this->key(),
            archetype: $this->archetype(),
            area: $this->area(),
            entityKey: 'detractors',
            title: $c['detractors'].' '.($c['detractors'] === 1 ? 'customer is' : 'customers are')
                .' sitting at detractor-level satisfaction',
            severity: Stats::severity($share / 0.08, 1.5, 4.5),
            confidence: Stats::confidence($c['total'], 60),
            facts: [
                'count' => $c['detractors'], 'share' => $share, 'value' => $value,
                'promoters' => $c['promoters'], 'avgSatisfaction' => $c['satisfaction'],
                'topName' => $top?->name ?? '', 'topLtv' => $top?->ltv ?? 0,
            ],
            evidence: [
                Signal::evidence('Detractors', $c['detractors'], null,
                    ['route' => 'customers', 'params' => ['filter' => 'detractors']]),
                Signal::evidence('Revenue they represent', $value, 'money'),
                Signal::evidence('Promoters', $c['promoters']),
                Signal::evidence('Avg satisfaction', number_format($c['satisfaction'], 2)),
            ],
        )];
    }
}
