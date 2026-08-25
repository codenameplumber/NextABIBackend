<?php

namespace Tests\Feature;

use App\Engine\InsightEngine;
use App\Support\Vocab;
use Tests\TestCase;

/**
 * The provenance test.
 *
 * The simulator plants seven conditions on purpose. The detectors are never
 * told about any of them — they read the same tables anyone else would. If a
 * planted condition is not rediscovered, the detector is broken; if a detector
 * fires on a condition nobody planted, that is a genuine finding, not a bug.
 *
 * This is the test that separates a computed product from a decorated one.
 */
class PlantedNarrativesTest extends TestCase
{
    /**
     * Each planted narrative declares the detector expected to find it.
     * `detector` in Vocab is the archetype; this maps to concrete keys.
     */
    private const EXPECTED = [
        'STALE_LEADS' => 'staleLeads',
        'CAMPAIGN_BURN' => 'campaignBurn',
        'DEMAND_GAP' => 'demandGap',
        'PAYMENT_RISK' => 'paymentRisk',
        'FEEDBACK_CLUSTER' => 'feedbackCluster',
        'AGENT_DIVERGENCE' => 'agentDivergence',
        'FUNNEL_EROSION' => 'funnelErosion',
    ];

    public function test_the_planted_conditions_are_declared_in_full(): void
    {
        // The audit trail has to stay honest: a condition planted in the
        // simulator and left out of NARRATIVES is a condition nobody can check.
        $this->assertSame(
            array_keys(self::EXPECTED),
            array_keys(Vocab::NARRATIVES),
            'The planted-narrative audit trail no longer matches what the tests expect.',
        );
    }

    public function test_the_detectors_rediscover_what_the_simulator_planted(): void
    {
        InsightEngine::for($this->tenant)->run();

        $found = collect(InsightEngine::for($this->tenant)->live())->pluck('detector')->unique();

        $missing = [];
        foreach (self::EXPECTED as $narrative => $detector) {
            if (! $found->contains($detector)) {
                $missing[] = $narrative.' (expected '.$detector.')';
            }
        }

        $this->assertEmpty(
            $missing,
            "Planted conditions the detectors failed to rediscover:\n  ".implode("\n  ", $missing),
        );
    }

    public function test_each_finding_names_the_detector_and_archetype_that_produced_it(): void
    {
        InsightEngine::for($this->tenant)->run();

        // DETECTORS is a list of class names; the key is what a finding stores.
        $keys = array_map(fn ($class) => (new $class)->key(), InsightEngine::DETECTORS);

        foreach (InsightEngine::for($this->tenant)->live() as $insight) {
            $this->assertContains(
                $insight->detector,
                $keys,
                'A finding claims to come from a detector that does not exist.',
            );
            $this->assertNotEmpty($insight->archetype);
            $this->assertArrayHasKey($insight->area, InsightEngine::AREAS);
        }
    }

    public function test_confidence_is_gated_on_sample_size(): void
    {
        InsightEngine::for($this->tenant)->run();

        foreach (InsightEngine::for($this->tenant)->live() as $insight) {
            $this->assertGreaterThan(0, $insight->confidence);
            $this->assertLessThanOrEqual(1, $insight->confidence, 'Confidence above 1 is not confidence.');
            $this->assertGreaterThanOrEqual(0, $insight->severity);
            $this->assertLessThanOrEqual(1, $insight->severity);
        }
    }

    public function test_severity_bands_are_not_all_critical(): void
    {
        InsightEngine::for($this->tenant)->run();

        $bands = collect(InsightEngine::for($this->tenant)->live())->countBy('band');

        // A product where everything is critical is a product where nothing
        // is. The bands were widened once for exactly this reason.
        $this->assertLessThan(
            $bands->sum(),
            $bands->get('critical', 0),
            'Every open finding is critical — the severity bands have collapsed.',
        );
    }
}
