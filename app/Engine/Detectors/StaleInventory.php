<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * Capital sitting in listings that are not moving.
 */
class StaleInventory implements Detector
{
    public function key(): string { return 'staleInventory'; }

    public function archetype(): string { return 'threshold'; }

    public function area(): string { return 'properties'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $p = $m->properties();

        if ($p['stale'] < 4 || $p['available'] < 1) {
            return [];
        }

        $share = Stats::safeDiv($p['stale'], $p['available']);
        if ($share < 0.09) {
            return [];
        }

        $worst = \App\Models\Property::where('status', 'available')
            ->orderByDesc('days_on_market')->first();

        return [new Signal(
            detector: $this->key(),
            archetype: $this->archetype(),
            area: $this->area(),
            entityKey: 'all',
            title: $p['stale'].' '.($p['stale'] === 1 ? 'listing has' : 'listings have')
                .' sat more than twice your median time on market',
            severity: Stats::severity(1 + $share * 2.5, 1.35, 3),
            confidence: Stats::confidence($p['stale'], 15),
            facts: [
                'count' => $p['stale'], 'value' => $p['staleValue'], 'share' => $share,
                'medianDom' => $p['domMedian'],
                'worstTitle' => $worst ? $worst->title.', '.$worst->location : '',
                'worstDays' => $worst?->days_on_market ?? 0,
                'worstPrice' => $worst?->price ?? 0,
                'carryCost' => $p['staleValue'] * 0.011,
            ],
            evidence: [
                Signal::evidence('Stale listings', $p['stale'], null,
                    ['route' => 'properties', 'params' => ['filter' => 'stale']]),
                Signal::evidence('Capital tied up', $p['staleValue'], 'money',
                    ['route' => 'properties', 'params' => ['filter' => 'stale', 'sort' => 'price']]),
                Signal::evidence('Median days on market', round($p['domMedian']).'d'),
                Signal::evidence('Worst', ($worst?->days_on_market ?? 0).'d', null,
                    ['route' => 'properties', 'params' => ['filter' => 'stale']]),
            ],
        )];
    }
}
