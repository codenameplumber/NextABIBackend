<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * A campaign spending heavily and converting far below the channel baseline.
 *
 * Smoothed rates again: a campaign with fourteen leads and zero closes is a
 * small sample, not a catastrophe, and saying otherwise once costs the
 * product its credibility on every later finding.
 */
class CampaignBurn implements Detector
{
    public function key(): string { return 'campaignBurn'; }

    public function archetype(): string { return 'cohort'; }

    public function area(): string { return 'marketing'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $mk = $m->marketing();
        $base = $mk['baselineConversion'] ?: 0.05;
        $out = [];

        foreach ($mk['campaigns'] as $c) {
            if ($c['leads'] < 15 || $c['spend'] <= 0) {
                continue;
            }

            $rate = Stats::smoothRate($c['won'], $c['leads'], $base);
            $ratio = Stats::ratio($base, $rate);
            $spendShare = Stats::safeDiv($c['spend'], $mk['spend']);

            if ($ratio < 1.8 || $spendShare < 0.035) {
                continue;
            }

            $wasted = $c['spend'] * (1 - min(1, Stats::safeDiv($c['conversion'], $base)));

            $out[] = new Signal(
                detector: $this->key(),
                archetype: $this->archetype(),
                area: $this->area(),
                entityKey: 'campaign:'.$c['id'],
                title: '"'.$c['name'].'" is converting '.number_format($ratio, 1)
                    .'x worse than your channel baseline',
                severity: Stats::severity($ratio * (1 + $spendShare * 2), 1.9, 6.5),
                confidence: Stats::confidence($c['leads'], 40),
                facts: [
                    'campaignId' => $c['id'], 'name' => $c['name'], 'channel' => $c['channel'],
                    'source' => $c['source'], 'spend' => $c['spend'], 'leads' => $c['leads'],
                    'won' => $c['won'], 'conversion' => $c['conversion'], 'baseline' => $base,
                    'cpl' => $c['cpl'], 'baselineCpl' => $mk['baselineCpl'], 'roas' => $c['roas'],
                    'multiple' => $ratio, 'spendShare' => $spendShare, 'wasted' => $wasted,
                ],
                evidence: [
                    Signal::evidence('Spend', $c['spend'], 'money',
                        ['route' => 'marketing', 'params' => ['campaign' => $c['id']]]),
                    Signal::evidence('Leads', $c['leads'], null,
                        ['route' => 'leads', 'params' => ['campaign' => $c['id']]]),
                    Signal::evidence('Closed', $c['won']),
                    Signal::evidence('Conversion', $c['conversion'], 'pct'),
                    Signal::evidence('Baseline', $base, 'pct'),
                    Signal::evidence('ROAS', number_format($c['roas'], 2).'x'),
                ],
            );
        }

        usort($out, fn ($a, $b) => $b->severity <=> $a->severity);

        return array_slice($out, 0, 2);
    }
}
