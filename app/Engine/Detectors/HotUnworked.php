<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

use App\Models\Lead;

/**
 * The best leads on the board, sitting idle.
 *
 * Narrower and more urgent than StaleLeads: high score, a week untouched.
 */
class HotUnworked implements Detector
{
    public function key(): string { return 'hotUnworked'; }

    public function archetype(): string { return 'threshold'; }

    public function area(): string { return 'leads'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $q = Lead::query()->where('is_open', true)
            ->where('score', '>=', 75)
            ->where('last_contact_at', '<=', now()->subDays(7));

        $count = (int) (clone $q)->count();
        if ($count < 6) {
            return [];
        }

        $value = (float) (clone $q)->sum('budget');
        $avgScore = (float) ((clone $q)->avg('score') ?? 0);
        $avgDays = (float) ((clone $q)->selectRaw('AVG(DATEDIFF(NOW(), last_contact_at)) d')
            ->value('d') ?? 0);

        return [new Signal(
            detector: $this->key(),
            archetype: $this->archetype(),
            area: $this->area(),
            entityKey: 'all',
            title: $count.' of your highest-scoring leads '
                .($count === 1 ? "hasn't" : "haven't").' been contacted in a week',
            severity: Stats::severity($count / 8, 1.2, 6),
            confidence: Stats::confidence($count, 15),
            facts: [
                'count' => $count,
                'value' => $value,
                'avgScore' => round($avgScore),
                'avgDays' => round($avgDays),
            ],
            evidence: [
                Signal::evidence('Hot and idle', $count, null,
                    ['route' => 'leads', 'params' => ['filter' => 'hot-idle']]),
                Signal::evidence('Combined budget', $value, 'money',
                    ['route' => 'leads', 'params' => ['filter' => 'hot-idle', 'sort' => 'budget']]),
                Signal::evidence('Average score', round($avgScore)),
            ],
        )];
    }
}
