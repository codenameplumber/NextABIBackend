<?php

namespace Tests\Feature;

use App\Engine\ActionBus;
use App\Engine\Metrics;
use App\Models\Lead;
use App\Support\TimeMachine;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The metric cache is not transactional, and that is a trap.
 *
 * Handlers call metrics() to measure the before/after diff, which repopulates
 * the cache from *uncommitted* state. If the surrounding transaction then
 * rolls back, the cache is left describing a world that no longer exists — and
 * the app serves those figures until they expire ten minutes later.
 *
 * It surfaced as Ask NexABI confidently reporting 549 stale leads against a
 * database holding 172, which is precisely the failure the whole product is
 * built to avoid.
 */
class CacheCoherenceTest extends TestCase
{
    public function test_the_cache_agrees_with_the_database_after_an_action(): void
    {
        $metrics = Metrics::for($this->tenant);
        $metrics->flush();
        $metrics->leads();                      // warm it

        ActionBus::for($this->tenant)->manual(
            type: 'SCHEDULE_FOLLOWUP',
            payload: ['filter' => 'stale', 'note' => 'coherence probe'],
            label: 'Coherence probe',
            key: 'test:coherence:'.uniqid(),
        );

        $this->assertSame(
            $this->countStaleDirectly(),
            Metrics::for($this->tenant)->leads()['stale'],
            'The cached figure survived an action that changed the rows underneath it.',
        );
    }

    public function test_a_refused_action_does_not_leave_a_poisoned_cache(): void
    {
        $metrics = Metrics::for($this->tenant);
        $metrics->flush();
        $before = $metrics->leads()['stale'];

        // A payload the handler will refuse. It still touches metrics() on the
        // way to deciding that, which is exactly how the cache got poisoned.
        $result = ActionBus::for($this->tenant)->manual(
            type: 'MATCH_LEADS',
            payload: [],
            label: 'Refused probe',
            key: 'test:refused:'.uniqid(),
        );

        $this->assertFalse($result['ok']);

        $this->assertSame(
            $before,
            Metrics::for($this->tenant)->leads()['stale'],
            'A refused action moved the figures.',
        );
        $this->assertSame($this->countStaleDirectly(), Metrics::for($this->tenant)->leads()['stale']);
    }

    public function test_a_rolled_back_probe_leaves_the_cache_honest(): void
    {
        // This is what nexabi:audit does: mutate heavily inside a transaction,
        // roll it back, and hand the database back untouched. The cache has to
        // come back too.
        $truth = $this->countStaleDirectly();

        DB::beginTransaction();

        try {
            Lead::where('is_open', true)->update(['last_contact_at' => now()->subDays(90)]);
            Metrics::for($this->tenant)->flush();
            Metrics::for($this->tenant)->leads();     // caches the doomed state
        } finally {
            DB::rollBack();
            Metrics::for($this->tenant)->flush();
        }

        $this->assertSame(
            $truth,
            Metrics::for($this->tenant)->leads()['stale'],
            'A rolled-back probe left the cache describing a world that was rolled away.',
        );
    }

    public function test_the_time_machine_leaves_the_cache_matching_the_new_world(): void
    {
        Metrics::for($this->tenant)->leads();     // warm on the old world

        (new TimeMachine($this->tenant))->advance(14);

        $this->assertSame(
            $this->countStaleDirectly(),
            Metrics::for($this->tenant)->leads()['stale'],
            'The clock moved and the cached figures did not.',
        );
    }

    /** The database's own answer, with no cache in the path. */
    private function countStaleDirectly(): int
    {
        return Lead::where('is_open', true)
            ->where('last_contact_at', '<=', now()->subDays(14))
            ->count();
    }
}
