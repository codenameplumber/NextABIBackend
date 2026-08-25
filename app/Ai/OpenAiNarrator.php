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
 *     rate limit, a timeout, a bad response — the card still fills. An AI
 *     outage should degrade the prose, not the product.
 *
 * Narration is cached against a hash of the facts that produced it, so a
 * finding is narrated once and re-narrated only when its numbers actually
 * move. At a scheduled run across many tenants, doing otherwise would cost
 * hundreds of thousands of calls a day for prose nobody reads.
 */
class OpenAiNarrator implements NarratorContract
{
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
                fallback: fn () => $this->fallback->narrate($insight),
            ),
        );

        $insight->forceFill(['narrative' => $text, 'narrative_hash' => $factsHash])->save();

        return $text;
    }

    public function brief(array $headline, array $insights): string
    {
        if (! $this->isLive()) {
            return $this->fallback->brief($headline, $insights);
        }

        $facts = collect($headline)->map(fn ($v, $k) => $k.': '
            .(is_float($v) && abs($v) > 1000 ? Fmt::moneyShort($v) : (is_float($v) ? round($v, 3) : $v)))
            ->implode("\n");

        $titles = collect($insights)->take(3)->map(fn ($i) => '- '.$i->title)->implode("\n");

        return $this->chat(
            system: 'You write the two-sentence opening line of a business dashboard for a '
                .'Nigerian estate agency. Sentence one: revenue and deals with the change on the '
                .'prior period. Sentence two: pipeline, overdue receivables, and how many findings '
                .'need a decision. Wrap every figure in **double asterisks**. Use only the figures '
                .'given. Plain English, no jargon, no preamble, exactly two sentences.',
            user: "FIGURES\n".$facts."\n\nOPEN FINDINGS\n".($titles ?: 'none'),
            fallback: fn () => $this->fallback->brief($headline, $insights),
        );
    }

    public function summarise(string $title, string $period, array $headline, array $insights): string
    {
        if (! $this->isLive()) {
            return $this->fallback->summarise($title, $period, $headline, $insights);
        }

        $facts = collect($headline)->map(fn ($v, $k) => $k.': '
            .(is_float($v) && abs($v) > 1000 ? Fmt::moneyShort($v) : (is_float($v) ? round($v, 3) : $v)))
            ->implode("\n");

        $titles = collect($insights)->take(5)->map(fn ($i) => '- '.$i->title)->implode("\n");

        return $this->chat(
            system: 'You write short executive summaries for a Nigerian estate agency. Four to six '
                .'sentences. Cover performance, pipeline, inventory and receivables, then name the '
                .'matters needing a decision. Wrap every figure in **double asterisks**. Use only '
                .'the figures given — never estimate, extrapolate or invent one. No headings, no '
                .'bullet points, no preamble.',
            user: "REPORT: {$title}\nPERIOD: {$period}\n\nFIGURES\n".$facts
                ."\n\nOPEN FINDINGS\n".($titles ?: 'none'),
            fallback: fn () => $this->fallback->summarise($title, $period, $headline, $insights),
        );
    }

    /* ------------------------------------------------------------ prompts */

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
                $value = abs($value) >= 10000
                    ? Fmt::moneyShort($value)
                    : round($value, 3);
            }

            $lines[] = $key.': '.$value;
        }

        foreach ($insight->evidence ?? [] as $e) {
            $lines[] = 'evidence · '.$e['label'].': '.Fmt::evidence($e);
        }

        return implode("\n", $lines);
    }

    /* --------------------------------------------------------------- http */

    private function chat(string $system, string $user, \Closure $fallback): string
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(20)
                ->retry(2, 400, throw: false)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'temperature' => 0.4,
                    'max_tokens' => 400,
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
