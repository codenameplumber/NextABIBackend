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

    /** Human-readable name, shown in Settings. */
    public function name(): string;

    public function isLive(): bool;
}
