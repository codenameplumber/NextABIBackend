<?php

namespace App\Ai;

use App\Models\Insight;

/**
 * THE SEAM.
 *
 * Everything else in NexABI computes. This is the only layer that generates.
 *
 *   - Analysis, detection, scoring, recommendation and action are real.
 *     They run over real rows and produce real, drillable numbers.
 *   - Language is the one thing arithmetic cannot do.
 *
 * So language lives behind this interface. The OpenAI implementation receives
 * `insight.facts` — figures already computed — and never raw rows. That keeps
 * it fast, cheap, and structurally incapable of inventing a number.
 *
 * If the key is missing or the call fails, the templated narrator answers
 * instead and the product carries on working. An AI outage should degrade the
 * prose, not the product.
 */
interface NarratorContract
{
    /** One or two paragraphs explaining a finding. */
    public function narrate(Insight $insight): string;

    /** Two sentences on where the business stands. */
    public function brief(array $headline, array $insights): string;

    /** Longer written summary for the reports screen. */
    public function summarise(string $title, string $period, array $headline, array $insights): string;

    /**
     * Answer a free-form question from a sheet of already-computed figures.
     *
     * The last resort behind Ask NexABI, reached only once the intent router
     * has failed to resolve the question itself. It receives the same kind of
     * input as everything else here — figures the metrics layer has already
     * computed, never rows — so it can phrase an answer and cannot invent one.
     *
     * Returns null when the facts do not contain the answer. That is the whole
     * point of the return type: a confidently wrong number is the one failure
     * mode that would discredit this product, so "I don't know" has to be
     * representable and has to be the default when anything is uncertain.
     */
    public function answer(string $question, array $facts): ?string;

    /** Human-readable name, shown in Settings. */
    public function name(): string;

    public function isLive(): bool;
}
