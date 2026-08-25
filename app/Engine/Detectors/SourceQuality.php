<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * A lead source consuming agent capacity without ever closing.
 *
 * Compares smoothed rates, so a source with sixty leads and one close is not
 * reported as "infinitely worse" than the median.
 */
class SourceQuality implements Detector
{
    public function key(): string { return 'sourceQuality'; }

    public function archetype(): string { return 'cohort'; }

    public function area(): string { return 'leads'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $l = $m->leads();
        $qualified = array_values(array_filter($l['bySource'], fn ($s) => $s['leads'] >= 40));

        if (count($qualified) < 4) {
            return [];
        }

        $median = Stats::median(array_column($qualified, 'conversion'));

        usort($qualified, fn ($a, $b) => $a['conversion'] <=> $b['conversion']);
        $worst = $qualified[0];
        $best = $qualified[count($qualified) - 1];

        $ratio = Stats::ratio($median, Stats::smoothRate($worst['won'], $worst['leads'], $median), 15);
        if ($ratio < 2.0 || $worst['leads'] < 60) {
            return [];
        }

        $share = Stats::safeDiv($worst['leads'], $l['total']);

        return [new Signal(
            detector: $this->key(),
            archetype: $this->archetype(),
            area: $this->area(),
            entityKey: $worst['key'],
            title: $worst['key'].' leads convert '.number_format($ratio, 1)
                .'x worse than your median channel',
            severity: Stats::severity($ratio * $share * 6, 1.4, 5),
            confidence: Stats::confidence($worst['leads'], 100),
            facts: [
                'source' => $worst['key'], 'leads' => $worst['leads'], 'won' => $worst['won'],
                'conversion' => $worst['conversion'], 'median' => $median,
                'multiple' => $ratio, 'bestSource' => $best['key'],
                'bestConversion' => $best['conversion'], 'share' => $share,
                'avgScore' => $worst['avgScore'],
            ],
            evidence: [
                Signal::evidence($worst['key'].' leads', $worst['leads'], null,
                    ['route' => 'leads', 'params' => ['source' => $worst['key']]]),
                Signal::evidence('Closed', $worst['won']),
                Signal::evidence('Conversion', $worst['conversion'], 'pct'),
                Signal::evidence('Median channel', $median, 'pct'),
                Signal::evidence('Best ('.$best['key'].')', $best['conversion'], 'pct',
                    ['route' => 'leads', 'params' => ['source' => $best['key']]]),
            ],
        )];
    }
}
