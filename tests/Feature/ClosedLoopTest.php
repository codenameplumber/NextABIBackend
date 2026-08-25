<?php

namespace Tests\Feature;

use App\Engine\ActionBus;
use App\Engine\InsightEngine;
use App\Engine\Metrics;
use App\Models\ActionLog;
use App\Models\Insight;
use App\Models\Lead;
use App\Models\Recommendation;
use Tests\TestCase;

/**
 * The central claim, tested.
 *
 * Data → analysis → insight → recommendation → action → changed data →
 * re-detection → the finding retires itself. If any link in that chain is
 * decorative rather than real, one of these fails.
 */
class ClosedLoopTest extends TestCase
{
    public function test_the_detectors_find_something_in_the_seeded_business(): void
    {
        $found = InsightEngine::for($this->tenant)->run();

        $this->assertGreaterThan(0, $found, 'The detectors found nothing in a business built to contain problems.');
    }

    public function test_every_finding_carries_evidence_and_a_way_to_act(): void
    {
        InsightEngine::for($this->tenant)->run();

        foreach (InsightEngine::for($this->tenant)->live() as $insight) {
            $this->assertNotEmpty($insight->evidence, "{$insight->detector} produced a finding with no evidence.");
            $this->assertNotEmpty($insight->facts, "{$insight->detector} produced a finding with no facts.");
            $this->assertGreaterThan(
                0,
                $insight->recommendations->count(),
                "{$insight->detector} produced a finding with nothing to do about it.",
            );
        }
    }

    public function test_applying_a_recommendation_changes_real_rows(): void
    {
        $before = Lead::where('is_open', true)
            ->where('last_contact_at', '<=', now()->subDays(14))->count();

        $this->assertGreaterThan(0, $before, 'No stale leads to clear — the fixture is wrong, not the code.');

        $result = ActionBus::for($this->tenant)->manual(
            type: 'SCHEDULE_FOLLOWUP',
            payload: ['filter' => 'stale', 'note' => 'test sweep'],
            label: 'Test sweep',
            key: 'test:sweep:1',
        );

        $this->assertTrue($result['ok'], $result['message']);

        $after = Lead::where('is_open', true)
            ->where('last_contact_at', '<=', now()->subDays(14))->count();

        $this->assertLessThan($before, $after, 'The action reported success but nothing moved.');
    }

    public function test_a_finding_retires_itself_once_the_condition_stops_holding(): void
    {
        $engine = InsightEngine::for($this->tenant);
        $engine->run();

        $insight = Insight::where('detector', 'staleLeads')->whereNull('resolved_at')->first();

        if (! $insight) {
            $this->markTestSkipped('No stale-lead finding in this fixture.');
        }

        ActionBus::for($this->tenant)->manual(
            type: 'SCHEDULE_FOLLOWUP',
            payload: ['filter' => 'stale', 'note' => 'test sweep'],
            label: 'Test sweep',
            key: 'test:retire:1',
        );

        // The action bus re-runs the detectors this action can have affected.
        // Nothing else tells the card to go away.
        $this->assertNotNull(
            $insight->fresh()->resolved_at,
            'The condition was fixed and the finding stayed open.',
        );
    }

    public function test_every_action_is_logged_with_a_before_and_after(): void
    {
        ActionBus::for($this->tenant)->manual(
            type: 'SCHEDULE_FOLLOWUP',
            payload: ['filter' => 'stale', 'note' => 'test sweep'],
            label: 'Test sweep',
            key: 'test:log:1',
        );

        $log = ActionLog::latest('id')->first();

        $this->assertSame('SCHEDULE_FOLLOWUP', $log->action_type);
        $this->assertNotEmpty($log->message);
        $this->assertGreaterThan(0, $log->affected);
    }

    public function test_a_double_click_cannot_apply_the_same_action_twice(): void
    {
        $args = [
            'type' => 'SCHEDULE_FOLLOWUP',
            'payload' => ['filter' => 'stale', 'note' => 'test sweep'],
            'label' => 'Test sweep',
            'key' => 'test:idempotent:1',
        ];

        $first = ActionBus::for($this->tenant)->manual(...$args);
        $second = ActionBus::for($this->tenant)->manual(...$args);

        $this->assertTrue($first['ok']);
        $this->assertFalse($second['ok'], 'The same action applied twice.');
        $this->assertSame(1, ActionLog::where('idempotency_key', hash('sha256', 'manual:test:idempotent:1'))->count());
    }

    public function test_a_recommendation_cannot_be_applied_twice(): void
    {
        InsightEngine::for($this->tenant)->run();

        $rec = Recommendation::whereNull('done_at')
            ->where('action_type', '!=', 'NAVIGATE')
            ->first();

        $this->assertNotNull($rec, 'No pending recommendation to test with.');

        $bus = new ActionBus($this->tenant);
        $first = $bus->execute($rec);

        if (! $first['ok']) {
            $this->markTestSkipped('This recommendation had nothing left to act on: '.$first['message']);
        }

        $second = $bus->execute($rec->fresh());
        $this->assertFalse($second['ok']);
    }

    public function test_dismissal_survives_re_detection(): void
    {
        $engine = InsightEngine::for($this->tenant);
        $engine->run();

        $insight = $engine->live()[0] ?? null;
        $this->assertNotNull($insight);

        $insight->forceFill(['dismissed_at' => now(), 'status' => 'dismissed'])->save();
        $fingerprint = $insight->fingerprint;

        // Re-running must not resurrect it. Without fingerprinting, a dismissed
        // finding reappears on the next scheduled run and the product becomes
        // something people learn to ignore.
        $engine->run();

        $this->assertNotNull(
            Insight::where('fingerprint', $fingerprint)->first()->dismissed_at,
            'A dismissed finding came back on the next detector run.',
        );
    }

    public function test_metrics_are_recomputed_after_an_action(): void
    {
        $metrics = Metrics::for($this->tenant);
        $before = $metrics->leads()['stale'];

        ActionBus::for($this->tenant)->manual(
            type: 'SCHEDULE_FOLLOWUP',
            payload: ['filter' => 'stale', 'note' => 'test sweep'],
            label: 'Test sweep',
            key: 'test:metrics:1',
        );

        $metrics->flush();

        $this->assertLessThan($before, $metrics->leads()['stale']);
    }
}
