<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * Qualified leads nobody has touched in a fortnight.
 *
 * The single highest-value finding in the product: these are not junk leads,
 * they are leads that were already working and went quiet.
 */
class StaleLeads implements Detector
{
    public function key(): string { return 'staleLeads'; }

    public function archetype(): string { return 'stagnation'; }

    public function area(): string { return 'leads'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $l = $m->leads();

        if ($l['stale'] < 5) {
            return [];
        }

        $ratio = Stats::safeDiv($l['stale'], max(1, $l['staleBaseline']));
        if ($ratio < 1.6) {
            return [];
        }

        $estLoss = $l['stalePipeline'] * 0.34;

        return [new Signal(
            detector: $this->key(),
            archetype: $this->archetype(),
            area: $this->area(),
            entityKey: 'all',
            title: $l['stale'].' open '.($l['stale'] === 1 ? 'lead has' : 'leads have')
                .' gone untouched for 14+ days',
            severity: Stats::severity($ratio, 1.6, 6),
            confidence: Stats::confidence($l['stale'], 25),
            facts: [
                'count' => $l['stale'],
                'pipeline' => $l['stalePipeline'],
                'baseline' => $l['staleBaseline'],
                'multiple' => $ratio,
                'oldestDays' => $l['staleOldestDays'],
                'avgScore' => round($l['staleAvgScore']),
                'estLoss' => $estLoss,
            ],
            evidence: [
                Signal::evidence('Stale leads', $l['stale'], null,
                    ['route' => 'leads', 'params' => ['filter' => 'stale']]),
                Signal::evidence('Pipeline at risk', $l['stalePipeline'], 'money',
                    ['route' => 'leads', 'params' => ['filter' => 'stale', 'sort' => 'budget']]),
                Signal::evidence('Normal backlog', $l['staleBaseline']),
                Signal::evidence('Oldest', $l['staleOldestDays'].'d', null,
                    ['route' => 'leads', 'params' => ['filter' => 'stale', 'sort' => 'oldest']]),
            ],
        )];
    }
}
