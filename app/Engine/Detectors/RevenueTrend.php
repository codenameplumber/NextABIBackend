<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * Revenue moving consistently in one direction across months.
 */
class RevenueTrend implements Detector
{
    public function key(): string { return 'revenueTrend'; }

    public function archetype(): string { return 'trend'; }

    public function area(): string { return 'sales'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $s = $m->sales(90);

        // Drop the current month — it is incomplete and would read as a fall.
        $series = array_values(array_filter($s['series'], fn ($p) => ! $p['partial']));
        $values = array_slice(array_column($series, 'value'), -8);

        if (count($values) < 6) {
            return [];
        }

        $trend = Stats::trendPct($values);
        $down = $trend < -0.055;
        $up = $trend > 0.055;

        if (! $down && ! $up) {
            return [];
        }

        $recent = Stats::edgeMean($values, 3, true);
        $prior = Stats::edgeMean($values, 3);

        return [new Signal(
            detector: $this->key(),
            archetype: $this->archetype(),
            area: $this->area(),
            entityKey: 'revenue',
            title: $down
                ? 'Revenue has been declining for '.count($values).' straight months'
                : 'Revenue is compounding - up '.round(abs($trend) * 100).'% per month',
            severity: $down ? Stats::severity(1 + abs($trend) * 6, 1.3, 2.4) : 0.22,
            confidence: Stats::confidence($s['deals'], 25),
            facts: [
                'direction' => $down ? 'down' : 'up',
                'trend' => $trend, 'recent' => $recent, 'prior' => $prior,
                'months' => count($values),
                'change' => Stats::safeDiv($recent - $prior, $prior),
                'deals' => $s['deals'], 'avgDeal' => $s['avgDeal'], 'winRate' => $s['winRate'],
            ],
            evidence: [
                Signal::evidence('Recent 3-mo average', $recent, 'money',
                    ['route' => 'sales', 'params' => []]),
                Signal::evidence('Prior 3-mo average', $prior, 'money'),
                Signal::evidence('Monthly trend', ($trend > 0 ? '+' : '').number_format($trend * 100, 1).'%'),
                Signal::evidence('Deals closed (90d)', $s['deals']),
            ],
        )];
    }
}
