<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * Too much revenue depending on too few people.
 *
 * A continuity risk, not a performance win.
 */
class RevenueConcentration implements Detector
{
    public function key(): string { return 'revenueConcentration'; }

    public function archetype(): string { return 'concentration'; }

    public function area(): string { return 'sales'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $a = $m->agents();

        if ($a['concentration'] < 0.52 || count($a['rows']) < 8) {
            return [];
        }

        $top3 = array_slice($a['rows'], 0, 3);
        $revenue = array_sum(array_column($top3, 'revenue'));

        return [new Signal(
            detector: $this->key(),
            archetype: $this->archetype(),
            area: $this->area(),
            entityKey: 'agents',
            title: round($a['concentration'] * 100).'% of revenue depends on just 3 agents',
            severity: Stats::severity($a['concentration'] / 0.35, 1.4, 2.4),
            confidence: 0.88,
            facts: [
                'concentration' => $a['concentration'],
                'names' => array_column($top3, 'name'),
                'revenue' => $revenue, 'total' => $a['totalRevenue'],
                'agents' => count($a['rows']),
            ],
            evidence: [
                Signal::evidence('Top-3 share', $a['concentration'], 'pct',
                    ['route' => 'sales', 'params' => ['view' => 'agents']]),
                Signal::evidence('Their revenue', $revenue, 'money'),
                Signal::evidence('Team size', count($a['rows'])),
                Signal::evidence('Healthy benchmark', 0.35, 'pct'),
            ],
        )];
    }
}
