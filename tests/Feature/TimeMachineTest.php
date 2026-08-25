<?php

namespace Tests\Feature;

use App\Engine\InsightEngine;
use App\Engine\Metrics;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Payment;
use App\Support\TimeMachine;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Time Machine.
 *
 * The whole point of the control is that advancing moves the *business*, not
 * just the date. On a frozen dataset a jump forward makes revenue fall off a
 * cliff and every trend collapse — which reads as a bug and destroys the one
 * claim the control exists to demonstrate.
 */
class TimeMachineTest extends TestCase
{
    public function test_advancing_ages_the_existing_business(): void
    {
        $lead = Lead::orderBy('id')->first();
        $before = $lead->created_at->copy();

        (new TimeMachine($this->tenant))->advance(7);

        $this->assertEqualsWithDelta(
            7,
            $lead->fresh()->created_at->diffInDays($before, absolute: true),
            0.1,
            'The existing history did not move with the clock.',
        );
    }

    public function test_advancing_brings_new_business_with_it(): void
    {
        $leadsBefore = Lead::count();

        $summary = (new TimeMachine($this->tenant))->advance(30);

        $this->assertGreaterThan(0, $summary['newLeads'], 'A month passed and no lead arrived.');
        $this->assertSame($leadsBefore + $summary['newLeads'], Lead::count());
    }

    public function test_a_closed_deal_gets_a_real_instalment_plan(): void
    {
        $summary = (new TimeMachine($this->tenant))->advance(60);

        if (! $summary['won']) {
            $this->markTestSkipped('No deal closed in this jump.');
        }

        $newest = Deal::where('won', true)->latest('id')->first();

        $this->assertGreaterThan(
            0,
            Payment::where('deal_id', $newest->id)->count(),
            'A deal closed with no receivables behind it.',
        );
        $this->assertNotNull($newest->customer_id);
    }

    public function test_revenue_does_not_collapse_across_a_jump(): void
    {
        $metrics = Metrics::for($this->tenant);
        $before = $metrics->sales(90)['revenue'];

        (new TimeMachine($this->tenant))->advance(30);
        $metrics->flush();

        $after = $metrics->sales(90)['revenue'];

        // The exact figure is allowed to move — that is the point. What it must
        // not do is fall to nothing, which is what happens if the clock moves
        // and the business does not move with it.
        $this->assertGreaterThan(
            $before * 0.25,
            $after,
            'Revenue collapsed across the jump — the business did not move with the clock.',
        );
    }

    public function test_days_on_market_moves_with_the_clock(): void
    {
        $before = \App\Models\Property::where('status', 'available')
            ->pluck('days_on_market', 'id');

        (new TimeMachine($this->tenant))->advance(14);

        // Only the listings that are STILL available. A unit that sold during
        // the jump has its days-on-market recomputed from the sale date, which
        // is correct and would look like a failure here.
        $after = \App\Models\Property::where('status', 'available')
            ->whereIn('id', $before->keys())
            ->pluck('days_on_market', 'id');

        $this->assertGreaterThan(0, $after->count());

        foreach ($after as $id => $days) {
            $this->assertSame(
                $before[$id] + 14,
                $days,
                'Listings did not age, so the stale-inventory detector reads the old world.',
            );
        }
    }

    public function test_leads_contacted_today_mostly_survive_the_jump(): void
    {
        Lead::where('is_open', true)->update(['last_contact_at' => now()]);
        $openBefore = Lead::where('is_open', true)->count();

        $summary = (new TimeMachine($this->tenant))->advance(14);

        // This is what makes the follow-up action matter across a jump: clear
        // the stale backlog first and materially fewer leads go cold.
        $this->assertLessThan(
            $openBefore * 0.35,
            $summary['lost'],
            'Leads contacted today went cold anyway, so follow-up does not matter.',
        );
    }

    public function test_the_detectors_re_run_after_a_jump(): void
    {
        Livewire::actingAs($this->staff())
            ->test(\App\Livewire\TimeMachine::class)
            ->call('advance', 7)
            ->assertOk();

        $this->assertGreaterThan(
            0,
            count(InsightEngine::for($this->tenant)->live()),
            'The clock moved and nothing was re-detected.',
        );
    }

    public function test_a_jump_reports_what_it_did(): void
    {
        $summary = (new TimeMachine($this->tenant))->advance(7);

        $this->assertSame(7, $summary['days']);
        $this->assertIsInt($summary['newLeads']);
        $this->assertGreaterThanOrEqual(0, $summary['lost']);
        $this->assertNotSame('', TimeMachine::describe($summary));
    }

    public function test_it_clamps_an_absurd_jump(): void
    {
        // Clamped rather than trusted: an unbounded value here is an unbounded
        // simulation, and this method is reachable from the command palette.
        $summary = (new TimeMachine($this->tenant))->advance(100000);

        $this->assertSame(180, $summary['days']);
    }
}
