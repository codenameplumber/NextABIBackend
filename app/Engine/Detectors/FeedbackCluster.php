<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * A complaint theme repeating far above the others.
 *
 * One complaint is noise. The same theme thirty times in sixty days is an
 * operational defect with a name.
 */
class FeedbackCluster implements Detector
{
    public function key(): string { return 'feedbackCluster'; }

    public function archetype(): string { return 'anomaly'; }

    public function area(): string { return 'feedback'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $fb = $m->feedback(60);

        if ($fb['window'] < 8) {
            return [];
        }

        $out = [];

        foreach ($fb['tags'] as $tag) {
            if ($tag['count'] < 6) {
                continue;
            }

            $others = array_column(
                array_values(array_filter($fb['tags'], fn ($x) => $x['key'] !== $tag['key'])),
                'count',
            );

            $z = Stats::zScore($tag['count'], array_map('floatval', $others));
            $negShare = Stats::safeDiv($tag['negative'], $tag['count']);

            if ($z < 1.4 || $negShare < 0.45) {
                continue;
            }

            $out[] = new Signal(
                detector: $this->key(),
                archetype: $this->archetype(),
                area: $this->area(),
                entityKey: 'tag:'.$tag['key'],
                title: '"'.$tag['key'].'" is now your most-cited complaint - '
                    .$tag['count'].' '.($tag['count'] === 1 ? 'mention' : 'mentions').' in 60 days',
                severity: Stats::severity(1 + $z / 2 + $negShare, 1.8, 4.2),
                confidence: Stats::confidence($tag['count'], 20),
                facts: [
                    'tag' => $tag['key'], 'count' => $tag['count'], 'prev' => $tag['prev'],
                    'change' => $tag['change'], 'negative' => $tag['negative'],
                    'negShare' => $negShare, 'avgRating' => $tag['avgRating'],
                    'overallRating' => $fb['avgRating'], 'ratingChange' => $fb['ratingChange'],
                    'zScore' => $z,
                ],
                evidence: [
                    Signal::evidence('Mentions (60d)', $tag['count'], null,
                        ['route' => 'feedback', 'params' => ['tag' => $tag['key']]]),
                    Signal::evidence('Prior 60d', $tag['prev']),
                    Signal::evidence('Negative', $tag['negative'], null,
                        ['route' => 'feedback', 'params' => ['tag' => $tag['key'], 'rating' => 'low']]),
                    Signal::evidence('Avg rating on tag', number_format($tag['avgRating'], 1)),
                    Signal::evidence('Overall rating', number_format($fb['avgRating'], 2)),
                ],
            );
        }

        usort($out, fn ($a, $b) => $b->severity <=> $a->severity);

        return array_slice($out, 0, 2);
    }
}
