<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * A segment drawing far more demand than its remaining stock can meet.
 *
 * Demand index is inquiries per AVAILABLE unit — the number that turns a
 * listings table into inventory intelligence.
 */
class DemandGap implements Detector
{
    public function key(): string { return 'demandGap'; }

    public function archetype(): string { return 'concentration'; }

    public function area(): string { return 'properties'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $p = $m->properties();
        $base = $p['baselineDemand'] ?: 1;
        $out = [];

        foreach ($p['segments'] as $s) {
            if ($s['available'] < 1 || $s['available'] > 12 || $s['inquiries'] < 40) {
                continue;
            }

            $ratio = Stats::safeDiv($s['demandIndex'], $base);
            if ($ratio < 2.2) {
                continue;
            }

            $unit = $s['available'] === 1 ? 'unit' : 'units';
            $beds = $s['bedrooms'] ? $s['bedrooms'].'-bed ' : '';

            $out[] = new Signal(
                detector: $this->key(),
                archetype: $this->archetype(),
                area: $this->area(),
                entityKey: $s['key'],
                title: $beds.$s['type'].' in '.$s['location'].' is drawing '
                    .number_format($ratio, 1).'x normal demand on '
                    .$s['available'].' '.$unit.' of stock',
                severity: Stats::severity($ratio, 2.2, 6),
                confidence: Stats::confidence($s['inquiries'], 60),
                facts: [
                    'location' => $s['location'], 'type' => $s['type'], 'bedrooms' => $s['bedrooms'],
                    'available' => $s['available'], 'inquiries' => $s['inquiries'],
                    'demandIndex' => $s['demandIndex'], 'baseline' => $base, 'multiple' => $ratio,
                    'avgPrice' => $s['avgPrice'], 'avgDom' => $s['avgDom'], 'sold' => $s['sold'],
                    'upside' => $s['avgPrice'] * 0.06,
                ],
                evidence: [
                    Signal::evidence('Inquiries', $s['inquiries'], null,
                        ['route' => 'properties', 'params' => ['location' => $s['location'], 'type' => $s['type']]]),
                    Signal::evidence('Available', $s['available'], null,
                        ['route' => 'properties', 'params' => [
                            'location' => $s['location'], 'type' => $s['type'], 'status' => 'available']]),
                    Signal::evidence('Inquiries per unit', number_format($s['demandIndex'], 1)),
                    Signal::evidence('Portfolio norm', number_format($base, 1)),
                    Signal::evidence('Avg days on market', round($s['avgDom']).'d'),
                ],
            );
        }

        usort($out, fn ($a, $b) => $b->severity <=> $a->severity);

        return array_slice($out, 0, 2);
    }
}
