<?php

namespace Tests\Feature;

use App\Ai\NarratorContract;
use App\Ai\OpenAiNarrator;
use App\Ai\TemplateNarrator;
use App\Engine\InsightEngine;
use App\Engine\Metrics;
use App\Models\Insight;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The AI seam.
 *
 * Everything else in NexABI computes; this is the only layer that generates.
 * Two properties have to hold: with no key the product works unchanged, and
 * with a key that fails the product still works unchanged. An AI outage should
 * degrade the prose, not the product.
 */
class NarrationTest extends TestCase
{
    public function test_without_a_key_the_templated_narrator_answers(): void
    {
        $narrator = app(NarratorContract::class);

        $this->assertFalse($narrator->isLive());
        $this->assertSame('NexABI Local Engine', $narrator->name());
        $this->assertInstanceOf(OpenAiNarrator::class, $narrator);
    }

    public function test_every_detector_produces_prose(): void
    {
        InsightEngine::for($this->tenant)->run();
        $narrator = app(NarratorContract::class);

        foreach (InsightEngine::for($this->tenant)->live() as $insight) {
            $text = $narrator->narrate($insight);

            $this->assertNotEmpty($text, "{$insight->detector} narrated to nothing.");
            $this->assertStringContainsString(
                '**',
                $text,
                "{$insight->detector} produced prose with no figures in it.",
            );
            $this->assertGreaterThan(
                40,
                mb_strlen($text),
                "{$insight->detector} produced a one-liner where an explanation belongs.",
            );
        }
    }

    public function test_a_missing_fact_never_blanks_a_card(): void
    {
        InsightEngine::for($this->tenant)->run();

        $insight = Insight::whereNull('resolved_at')->firstOrFail();
        $insight->forceFill(['facts' => []])->save();

        $text = app(NarratorContract::class)->narrate($insight->fresh());

        $this->assertNotEmpty($text, 'An insight with no facts produced an empty card.');
    }

    public function test_the_dashboard_brief_is_short_and_carries_figures(): void
    {
        $m = Metrics::for($this->tenant);
        InsightEngine::for($this->tenant)->run();

        $brief = app(NarratorContract::class)->brief(
            $m->headline(),
            InsightEngine::for($this->tenant)->feed('ceo', 8),
        );

        $this->assertStringContainsString('**', $brief);
        // Two sentences. The decision bar directly beneath already names the
        // thing most worth doing, so a paragraph here costs attention and buys
        // nothing.
        $this->assertLessThanOrEqual(3, substr_count($brief, '. '));
    }

    public function test_a_report_summary_only_uses_the_facts_it_was_given(): void
    {
        $narrator = new TemplateNarrator;

        // Receivables figures only — as the Reports screen scopes them.
        $summary = $narrator->summarise('Receivables review', 'current', [
            'collected' => 1.0e9,
            'outstanding' => 4.0e8,
            'overdueAmount' => 2.5e8,
            'collectionRatio' => 0.91,
            'expected30' => 8.0e7,
        ], []);

        $this->assertStringContainsString('Receivables show', $summary);
        $this->assertStringNotContainsString('win rate', $summary);
        $this->assertStringNotContainsString('Inventory', $summary);
    }

    /**
     * The path the dashboard actually takes.
     *
     * Every previous live-model test narrated an insight fetched straight from
     * the database. The dashboard does not — it narrates whatever came back
     * from feed(), and feed() decorates each row with a `role_priority`
     * attribute for sorting that has no column behind it. Saving the whole
     * dirty model then wrote that into the UPDATE and the query failed.
     *
     * It only ever broke with a live key, because the templated narrator never
     * writes anything — which is exactly the sort of bug a fallback hides until
     * the day you stop falling back.
     */
    public function test_narrating_a_ranked_finding_persists_without_its_sort_key(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Revenue is **₦1.2B** this period.']]],
        ])]);

        config(['services.openai.enabled' => true, 'services.openai.key' => 'sk-test']);
        app()->forgetInstance(NarratorContract::class);

        InsightEngine::for($this->tenant)->run();

        // Through feed(), not straight from the table — that is the difference.
        $feed = InsightEngine::for($this->tenant)->feed('ceo', 1);
        $this->assertNotEmpty($feed, 'The fixture should produce at least one finding.');

        $insight = $feed[0];
        $this->assertNotNull($insight->getAttribute('role_priority'),
            'feed() should still be attaching the ranking this test guards.');

        $text = app(NarratorContract::class)->narrate($insight);

        $this->assertNotSame('', trim($text));
        $this->assertSame($text, $insight->fresh()->narrative,
            'The narrative has to reach the database, not just the response.');
    }

    /**
     * Repeated renders must not be repeated spend.
     *
     * narrate() was cached from the start; brief() and summarise() were not,
     * and both are called on render. That meant every dashboard load was an API
     * call, and the Reports switcher was five of them — seven hundred tokens
     * each — for prose that had not changed because the figures had not.
     *
     * The digest is the expensive one: it asks for a brief per recipient, so it
     * multiplies by staff and again by tenant.
     */
    public function test_repeated_renders_do_not_repeat_the_spend(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Revenue is **₦1.2B**.']]],
        ])]);

        config(['services.openai.enabled' => true, 'services.openai.key' => 'sk-test']);
        app()->forgetInstance(NarratorContract::class);

        $metrics = Metrics::for($this->tenant);
        $headline = $metrics->headline();
        $feed = InsightEngine::for($this->tenant)->feed('ceo', 3);
        $narrator = app(NarratorContract::class);

        for ($i = 0; $i < 5; $i++) {
            $narrator->brief($headline, $feed);
            $narrator->summarise('Sales', '90 days', $headline, $feed);
        }

        // Ten renders, two distinct prompts, two upstream calls.
        Http::assertSentCount(2);
    }

    public function test_a_failing_model_falls_back_instead_of_breaking_the_page(): void
    {
        // 429 is the rate limit. Whatever the cause, the card has to fill.
        Http::fake(['api.openai.com/*' => Http::response('rate limited', 429)]);

        InsightEngine::for($this->tenant)->run();
        $insight = Insight::whereNull('resolved_at')->firstOrFail();

        $fallback = new TemplateNarrator;
        $live = new OpenAiNarrator($fallback, 'sk-test-key-not-real', 'gpt-4o-mini');

        $this->assertTrue($live->isLive());

        $text = $live->narrate($insight);

        $this->assertNotEmpty($text);
        $this->assertSame(
            $fallback->narrate($insight),
            $text,
            'A failed model call did not fall through to the templated narrator.',
        );
    }

    public function test_a_live_model_receives_facts_and_never_rows(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Generated sentence with **a figure**.']]],
        ])]);

        InsightEngine::for($this->tenant)->run();
        $insight = Insight::whereNull('resolved_at')->firstOrFail();

        $live = new OpenAiNarrator(new TemplateNarrator, 'sk-test-key-not-real');
        $this->assertSame('Generated sentence with **a figure**.', $live->narrate($insight));

        Http::assertSent(function ($request) {
            $body = json_encode($request->data());

            // The prompt carries the finding's computed facts, and nothing
            // that looks like a row dump.
            $this->assertStringContainsString('FINDING:', $body);
            $this->assertStringNotContainsString('SELECT', $body);
            $this->assertStringNotContainsString('tenant_id', $body);

            // The bearer token has to be on the request, or the call never
            // reaches a model at all.
            $this->assertStringStartsWith(
                'Bearer ',
                $request->header('Authorization')[0] ?? '',
            );

            return true;
        });
    }
}
