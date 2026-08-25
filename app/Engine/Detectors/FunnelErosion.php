<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * Viewing-to-negotiating conversion decaying over the trailing weeks.
 *
 * Fitted to a smoothed series: a weekly rate built on five to twelve leads
 * swings 0-100% on sample noise, and a slope through that measures the noise.
 */
class FunnelErosion implements Detector
{
    public function key(): string { return 'funnelErosion'; }

    public function archetype(): string { return 'funnel'; }

    public function area(): string { return 'sales'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $f = $m->funnel();
        $series = array_values(array_filter($f['weeklyStepConversion'], fn ($w) => $w['n'] >= 4));

        if (count($series) < 6) {
            return [];
        }

        $raw = array_column($series, 'value');
        $smooth = Stats::smooth($raw);

        $trend = Stats::trendPct($smooth);
        if ($trend > -0.03) {
            return [];
        }

        $early = Stats::edgeMean($smooth, 3);
        $recent = Stats::edgeMean($smooth, 3, true);
        $drop = $early - $recent;

        if ($drop < 0.09) {
            return [];
        }

        $stage = null;
        foreach ($f['stages'] as $s) {
            if ($s['stage'] === 'negotiating') {
                $stage = $s;
            }
        }

        return [new Signal(
            detector: $this->key(),
            archetype: $this->archetype(),
            area: $this->area(),
            entityKey: 'viewing-negotiating',
            title: 'Viewing to negotiating conversion has fallen '
                .round($drop * 100).' points over '.count($series).' weeks',
            severity: Stats::severity(1 + $drop * 3, 1.4, 3.1),
            confidence: Stats::confidence(array_sum(array_column($series, 'n')), 80),
            facts: [
                'recent' => $recent, 'early' => $early, 'drop' => $drop,
                'weeks' => count($series), 'trend' => $trend,
                'dropped' => $stage['dropped'] ?? 0,
            ],
            evidence: [
                Signal::evidence('Now', $recent, 'pct'),
                Signal::evidence(count($series).' weeks ago', $early, 'pct'),
                Signal::evidence('Leads lost at step', $stage['dropped'] ?? 0, null,
                    ['route' => 'sales', 'params' => ['view' => 'funnel']]),
                Signal::evidence('Weekly trend', number_format($trend * 100, 1).'%/wk'),
            ],
        )];
    }
}
