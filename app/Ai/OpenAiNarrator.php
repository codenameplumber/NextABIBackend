<?php

namespace App\Ai;

use App\Models\Insight;
use App\Support\Fmt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI-backed narration.
 *
 * Two rules make this safe to put in front of customers:
 *
 *  1. The model receives FACTS, never rows. Every figure in the prompt has
 *     already been computed by the metrics layer, so the model's only job is
 *     phrasing. It is structurally incapable of inventing a number.
 *
 *  2. Every failure falls through to the templated narrator. A missing key, a
 *     rate limit, a timeout, a malformed response — the card still fills. An AI
 *     outage should degrade the prose, not the product.
 *
 * Narration is cached against a hash of the facts that produced it, so a
 * finding is narrated once and re-narrated only when its numbers actually
 * move. Across a scheduled sweep over many tenants, doing otherwise would cost
 * hundreds of thousands of calls a day for prose nobody reads.
 */
class OpenAiNarrator implements NarratorContract
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private TemplateNarrator $fallback,
        private ?string $apiKey = null,
        private string $model = 'gpt-4o-mini',
    ) {}

    public function name(): string
    {
        return $this->isLive() ? 'OpenAI · '.$this->model : $this->fallback->name();
    }

    public function isLive(): bool
    {
        return filled($this->apiKey);
    }

    public function narrate(Insight $insight): string
    {
        if (! $this->isLive()) {
            return $this->fallback->narrate($insight);
        }

        $factsHash = hash('sha256', json_encode($insight->facts));

        // Already narrated for exactly these numbers.
        if ($insight->narrative && $insight->narrative_hash === $factsHash) {
            return $insight->narrative;
        }

        $text = Cache::remember(
            'nexabi:narrative:'.$factsHash,
            now()->addDays(7),
            fn () => $this->chat(
                system: $this->narrateSystemPrompt(),
                user: $this->factSheet($insight),
                maxTokens: 400,
                fallback: fn () => $this->fallback->narrate($insight),
            ),
        );

        /**
         * Written as a targeted update, not save().
         *
         * An Insight that arrived through InsightEngine::feed() carries a
         * `role_priority` attribute set for sorting — it is a computed ranking
         * for the current seat, not a column. `save()` writes every dirty
         * attribute, so it put that in the UPDATE and the query failed with
         * "Unknown column 'role_priority'".
         *
         * It only ever broke with a live key, because the templated narrator
         * never writes anything — which is exactly the sort of bug a fallback
         * hides until the day you stop falling back. Naming the two columns
         * means no attribute anyone attaches later can reach the database
         * through this path.
         */
        Insight::withoutTenantScope()
            ->whereKey($insight->getKey())
            ->update(['narrative' => $text, 'narrative_hash' => $factsHash]);

        // Keep the in-memory copy in step, without marking it dirty.
        $insight->setRawAttributes(
            ['narrative' => $text, 'narrative_hash' => $factsHash] + $insight->getAttributes(),
            sync: true,
        );

        return $text;
    }

    public function brief(array $headline, array $insights): string
    {
        if (! $this->isLive()) {
            return $this->fallback->brief($headline, $insights);
        }

        $user = "FIGURES\n".$this->facts($headline)
            ."\n\nOPEN FINDINGS\n".$this->titles($insights, 3);

        /**
         * Cached against the figures that produced it, exactly as narrate() is.
         *
         * The dashboard asks for this on every render, and the morning digest
         * asks once per recipient — six admins on one tenant is six identical
         * calls for six identical inputs, and it multiplies by tenant. The
         * reasoning that put a cache on narrate() applies here and had simply
         * not been carried across.
         *
         * A shorter window than narrate(): a finding's prose is valid until its
         * numbers move, but a briefing is about today, and the headline figures
         * shift as the day goes on.
         */
        return Cache::remember(
            'nexabi:brief:'.hash('sha256', $user),
            now()->addHours(6),
            fn () => $this->chat(
                system: 'You write the two-sentence opening line of a business dashboard for a '
                    .'Nigerian estate agency. Sentence one: revenue and deals with the change on the '
                    .'prior period. Sentence two: pipeline, overdue receivables, and how many findings '
                    .'need a decision. Wrap every figure in **double asterisks**. Use only the figures '
                    .'given. Plain English, no jargon, no preamble, exactly two sentences.',
                user: $user,
                maxTokens: 300,
                fallback: fn () => $this->fallback->brief($headline, $insights),
            ),
        );
    }

    public function summarise(string $title, string $period, array $headline, array $insights): string
    {
        if (! $this->isLive()) {
            return $this->fallback->summarise($title, $period, $headline, $insights);
        }

        $user = "REPORT: {$title}\nPERIOD: {$period}\n\nFIGURES\n".$this->facts($headline)
            ."\n\nOPEN FINDINGS\n".$this->titles($insights, 5);

        /**
         * Cached, for the same reason narrate() is.
         *
         * The Reports screen carries a switcher across five written summaries
         * and regenerates on every render. Uncached, clicking through them once
         * is five calls of seven hundred tokens each, and clicking back is five
         * more — for prose that has not changed because the figures have not.
         */
        return Cache::remember(
            'nexabi:summary:'.hash('sha256', $user),
            now()->addHours(6),
            fn () => $this->chat(
                system: 'You write short executive summaries for a Nigerian estate agency. Four to six '
                    .'sentences. Cover performance, pipeline, inventory and receivables, then name the '
                    .'matters needing a decision. Wrap every figure in **double asterisks**. Use only '
                    .'the figures given — never estimate, extrapolate or invent one. No headings, no '
                    .'bullet points, no preamble.',
                user: $user,
                maxTokens: 700,
                fallback: fn () => $this->fallback->summarise($title, $period, $headline, $insights),
            ),
        );
    }

    /**
     * The last resort behind Ask NexABI.
     *
     * Reached only when the intent router could not resolve the question. The
     * model gets the same treatment as everywhere else in this class — figures
     * the metrics layer already computed, never rows — plus one extra rule
     * that matters more than the others: if the sheet does not contain the
     * answer, reply with a sentinel rather than reasoning towards one.
     *
     * That sentinel is why this returns ?string. A model asked a question it
     * cannot answer will otherwise produce something plausible, and a
     * plausible wrong number in a BI product is worse than no answer at all —
     * it is the single failure that would make an operator stop trusting every
     * other figure on the screen.
     */
    public function answer(string $question, array $facts): ?string
    {
        if (! $this->isLive()) {
            return $this->fallback->answer($question, $facts);
        }

        $sheet = $this->facts($facts);

        // Keyed on the question AND the figures, so the same question asked
        // after the numbers move is answered again rather than served stale.
        $text = Cache::remember(
            'nexabi:ask:'.hash('sha256', mb_strtolower(trim($question)).'|'.$sheet),
            now()->addHours(6),
            fn () => $this->chat(
                system: $this->answerSystemPrompt(),
                user: "QUESTION\n{$question}\n\nFIGURES AVAILABLE\n{$sheet}",
                maxTokens: 450,
                // A failed call is indistinguishable from an unanswerable
                // question here, and both should decline rather than guess.
                fallback: fn () => self::NO_DATA,
            ),
        );

        $clean = trim($text);

        // Bare sentinel, or a model that wrapped it in a sentence anyway.
        if ($clean === '' || str_contains(mb_strtoupper($clean), self::NO_DATA)) {
            return null;
        }

        return $clean;
    }

    /* ------------------------------------------------------------ prompts */

    /** What the model must say when the figures do not cover the question. */
    private const NO_DATA = 'NO_DATA';

    private function answerSystemPrompt(): string
    {
        return <<<'PROMPT'
        You answer questions about a Nigerian real-estate agency, for its owner.

        You are given a sheet of figures that have ALREADY been computed from the
        agency's live database. That sheet is the only thing you know.

        RULES
        - Answer ONLY from the figures given. Never estimate, extrapolate,
          calculate a new figure, or use general knowledge about real estate.
        - If the figures do not contain what was asked, reply with exactly
          NO_DATA and nothing else. Do not apologise, do not suggest anything,
          do not answer a nearby question instead. This is the most important
          rule here: a wrong number costs more than a missing one.
        - Partial coverage still counts as coverage — answer the part you can,
          and say plainly which part you cannot see.
        - Wrap every figure in **double asterisks** so the interface can bold it.
        - Two to four sentences. Lead with the direct answer, then the context
          that makes it actionable.
        - Plain English. No jargon, no bullet points, no headings, no preamble.
        - Currency is Naira and arrives pre-formatted. Reproduce it exactly.
        PROMPT;
    }

    private function narrateSystemPrompt(): string
    {
        return <<<'PROMPT'
        You explain business findings to the owner of a Nigerian real-estate agency.

        RULES
        - Use ONLY the figures in the fact sheet. Never estimate, extrapolate or
          invent a number. If a figure is not given, do not mention it.
        - Wrap every figure in **double asterisks** so the interface can bold it.
        - Two or three sentences. Lead with the number that matters most.
        - Plain English. No jargon, no bullet points, no headings, no preamble.
        - Say what it means for the business, not what the detector did.
        - Currency is Naira and already formatted. Reproduce it exactly as given.
        PROMPT;
    }

    /** Facts only — the model never sees a row. */
    private function factSheet(Insight $insight): string
    {
        $lines = ["FINDING: {$insight->title}", "AREA: {$insight->area}"];

        foreach ($insight->facts ?? [] as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_map(
                    fn ($v) => is_scalar($v) ? (string) $v : json_encode($v),
                    array_slice($value, 0, 6),
                ));
            }

            if (is_float($value)) {
                // Money-scale values arrive pre-formatted so the model cannot
                // reformat them into something that looks precise and is not.
                $value = abs($value) >= 10000 ? Fmt::moneyShort($value) : round($value, 3);
            }

            $lines[] = $key.': '.$value;
        }

        foreach ($insight->evidence ?? [] as $e) {
            $lines[] = 'evidence · '.$e['label'].': '.Fmt::evidence($e);
        }

        return implode("\n", $lines);
    }

    private function facts(array $headline): string
    {
        return collect($headline)->map(function ($v, $k) {
            if (is_float($v) && abs($v) > 1000) {
                return $k.': '.Fmt::moneyShort($v);
            }

            return $k.': '.(is_float($v) ? round($v, 3) : $v);
        })->implode("\n");
    }

    private function titles(array $insights, int $take): string
    {
        return collect($insights)->take($take)->map(fn ($i) => '- '.$i->title)->implode("\n")
            ?: 'none';
    }

    /* --------------------------------------------------------------- http */

    private function chat(string $system, string $user, int $maxTokens, \Closure $fallback): string
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(25)
                // 429 and 5xx are the two tells that waiting is the right move,
                // and `retry` handles both without giving up on the first one.
                ->retry(2, 500, throw: false)
                ->post(self::ENDPOINT, [
                    'model' => $this->model,
                    'temperature' => 0.4,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('[openai] request failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);

                return $fallback();
            }

            $text = trim((string) $response->json('choices.0.message.content'));

            return $text !== '' ? $text : $fallback();
        } catch (\Throwable $e) {
            Log::warning('[openai] threw', ['message' => $e->getMessage()]);

            return $fallback();
        }
    }
}
