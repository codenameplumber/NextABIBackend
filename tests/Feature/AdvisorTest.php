<?php

namespace Tests\Feature;

use App\Ai\ClientAdvisor;
use App\Ai\RealtorAdvisor;
use App\Models\Agent;
use App\Models\CommissionPayout;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Tests\TestCase;

/**
 * The two Ask surfaces outside the console.
 *
 * The suite runs with the model disabled, which is the strict case: anything
 * the routers cannot place falls through to a refusal. Every assertion here is
 * on the ROUTE taken and the SCOPE of what came back, never on wording — a
 * live key changes the prose and must not change either of those.
 */
class AdvisorTest extends TestCase
{
    private function buyer(): User
    {
        return User::where('email', 'client@example.com')->firstOrFail();
    }

    private function seller(): User
    {
        return User::where('email', 'realtor@anchorstone.ng')->firstOrFail();
    }

    private function buyerAsk(): ClientAdvisor
    {
        return new ClientAdvisor($this->buyer()->investor);
    }

    private function sellerAsk(): RealtorAdvisor
    {
        return new RealtorAdvisor($this->seller()->realtor);
    }

    /* ======================================================== the buyer */

    public function test_a_monthly_budget_returns_units_that_actually_fit_it(): void
    {
        $budget = 3_000_000;
        $a = $this->buyerAsk()->ask("What can I afford at NGN {$budget} a month?");

        $this->assertSame('properties', $a['kind']);
        $this->assertNotEmpty($a['items'], 'A budget with stock under it returned nothing.');

        // The promise on screen is "these fit". Every row has to hold, or the
        // feature is worse than the price filter it replaces.
        foreach ($a['items'] as $item) {
            $p = Property::where('ref', $item['ref'])->firstOrFail();
            $lowest = $p->instalmentFor(max($p->durationOptions()));

            $this->assertLessThanOrEqual(
                $budget,
                $lowest,
                "{$p->ref} was offered as affordable but its lowest instalment is {$lowest}.",
            );
        }
    }

    public function test_it_reads_a_budget_written_the_way_people_write_it(): void
    {
        // "2 million" and "500k" are how the figure is actually typed. A parser
        // that only understands 2000000 answers a different question.
        $written = [
            'What can I afford at 2 million a month?',
            'what can i afford at 2,000,000 a month',
            'I have 2000000 a month to spend',
        ];

        foreach ($written as $q) {
            $a = $this->buyerAsk()->ask($q);

            $this->assertContains($a['kind'], ['properties', 'none'],
                "Failed to read a budget out of: {$q}");
            $this->assertStringNotContainsString('How much a month', $a['headline'],
                "Asked for a figure that was already given: {$q}");
        }
    }

    public function test_asking_where_without_a_number_is_a_market_question(): void
    {
        // "Which areas are most affordable" contains the word "afford" but is
        // not a budget question, and replying by demanding a figure the buyer
        // never mentioned is answering something they did not ask.
        $a = $this->buyerAsk()->ask('Which areas are most affordable?');

        $this->assertSame('properties', $a['kind']);
        $this->assertNotEmpty($a['items']);
        $this->assertStringNotContainsString('How much a month', $a['headline']);
    }

    public function test_the_buyer_is_told_about_their_own_file_and_nobody_elses(): void
    {
        $a = $this->buyerAsk()->ask('How much do I still owe?');

        $this->assertSame('file', $a['kind']);

        $text = $a['headline'].' '.$a['narrative'];

        foreach (['buyer2@example.com', 'buyer3@example.com', 'buyer4@example.com'] as $other) {
            $name = User::where('email', $other)->firstOrFail()->name;
            $this->assertStringNotContainsString($name, $text,
                'Another buyer appeared in this buyer\'s answer.');
        }
    }

    public function test_it_declines_rather_than_giving_financial_advice(): void
    {
        // Nothing in this database knows whether an area is a good investment,
        // and a confident answer would be an opinion dressed as a calculation.
        $a = $this->buyerAsk()->ask('Will property in Lekki go up in value next year?');

        $this->assertSame('none', $a['kind']);
    }

    /* ====================================================== the realtor */

    public function test_the_realtor_is_told_where_demand_outruns_supply(): void
    {
        $a = $this->sellerAsk()->ask('Which location should I focus my marketing on?');

        $this->assertSame('ranking', $a['kind']);
        $this->assertNotEmpty($a['rows'], 'No areas came back to focus on.');

        // Demand without the stock to sell into it is half an answer — an area
        // everybody wants and nobody has is an area you cannot work.
        $this->assertContains('Units available', $a['columns']);
    }

    public function test_movement_is_reported_not_just_the_biggest_area(): void
    {
        $a = $this->sellerAsk()->ask('Which areas are buyers becoming interested in?');

        $this->assertSame('ranking', $a['kind']);
        $this->assertContains('Movement', $a['columns'],
            'An area that is already the largest is not news; a change in it is.');
    }

    public function test_own_performance_is_scoped_to_their_own_book(): void
    {
        $realtor = $this->seller()->realtor;
        $a = $this->sellerAsk()->ask('How are my own leads converting?');

        $this->assertSame('ranking', $a['kind']);

        $mine = Lead::where('assigned_agent_id', $realtor->id)->count();
        $this->assertStringContainsString(number_format($mine), $a['narrative'],
            'The figure quoted is not this realtor\'s own lead count.');
    }

    public function test_a_realtor_cannot_ask_about_another_realtors_numbers(): void
    {
        $mine = $this->seller()->agent_id;
        $a = $this->sellerAsk()->ask('Who is the best realtor in the company?');

        // Demand is market-wide; performance is private. No route returns a
        // colleague's book and the facts sheet carries no other names.
        $this->assertSame('none', $a['kind']);

        $text = $a['headline'].' '.$a['narrative'];

        foreach (Agent::query()->approved()->where('id', '!=', $mine)->limit(25)->pluck('name') as $name) {
            $this->assertStringNotContainsString($name, $text,
                "Another realtor ({$name}) leaked into the answer.");
        }
    }

    public function test_commission_reported_is_their_own_accrual(): void
    {
        $realtor = $this->seller()->realtor;
        $owed = (float) CommissionPayout::where('agent_id', $realtor->id)
            ->where('status', 'owed')->sum('amount');

        $a = $this->sellerAsk()->ask('How much commission am I owed?');

        $this->assertContains($a['kind'], ['file', 'none']);

        if ($a['kind'] === 'file') {
            $this->assertStringContainsString(number_format($owed), $a['headline']);
        }
    }

    /* ========================================================== surfaces */

    public function test_both_ask_pages_render_for_the_right_person(): void
    {
        $this->actingAs($this->buyer())->get('/portal/ask')->assertOk()->assertSee('Ask NexABI');
        $this->actingAs($this->seller())->get('/realtor/ask')->assertOk()->assertSee('Ask NexABI');
    }

    public function test_a_buyer_sees_their_realtor_on_every_portal_page(): void
    {
        $me = $this->buyer();
        $realtorName = $me->investor->realtor?->name;

        $this->assertNotNull($realtorName, 'This fixture needs an assigned realtor.');

        // Browsing is exactly when a buyer has questions the software cannot
        // answer, so the person who can must not be behind a tab.
        foreach (['/portal', '/portal/browse', '/portal/ask', '/portal/payments'] as $path) {
            $this->actingAs($me)->get($path)
                ->assertOk()
                ->assertSee('Your realtor')
                ->assertSee($realtorName);
        }
    }
}
