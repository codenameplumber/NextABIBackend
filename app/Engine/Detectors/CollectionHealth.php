<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * Cash coming in against what has actually fallen due.
 */
class CollectionHealth implements Detector
{
    public function key(): string { return 'collectionHealth'; }

    public function archetype(): string { return 'threshold'; }

    public function area(): string { return 'finance'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $f = $m->finance();

        if ($f['collectionRatio'] >= 0.9 || ! $f['outstanding']) {
            return [];
        }

        return [new Signal(
            detector: $this->key(),
            archetype: $this->archetype(),
            area: $this->area(),
            entityKey: 'ratio',
            title: 'Collection ratio has slipped to '.round($f['collectionRatio'] * 100).'%',
            severity: Stats::severity(0.92 / max($f['collectionRatio'], 0.2), 1.03, 1.5),
            confidence: 0.92,
            facts: [
                'ratio' => $f['collectionRatio'], 'outstanding' => $f['outstanding'],
                'collected' => $f['collected'], 'aging' => $f['aging'],
                'expected30' => $f['expected30'],
            ],
            evidence: [
                Signal::evidence('Collection ratio', $f['collectionRatio'], 'pct',
                    ['route' => 'finance', 'params' => []]),
                Signal::evidence('Target', 0.92, 'pct'),
                Signal::evidence('Outstanding', $f['outstanding'], 'money',
                    ['route' => 'finance', 'params' => ['filter' => 'outstanding']]),
                Signal::evidence('Due next 30d', $f['expected30'], 'money'),
            ],
        )];
    }
}
