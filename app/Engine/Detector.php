<?php

namespace App\Engine;

use App\Models\Tenant;

/**
 * Layer 3 — Detectors.
 *
 * Seven archetypes cover essentially all of business intelligence:
 *
 *   threshold | trend | anomaly | cohort | stagnation | funnel | concentration
 *
 * Each detector is a pure function of the metrics layer that returns zero or
 * more Signals. Returning zero is a first-class outcome — if the business is
 * healthy, NexABI says nothing. That is what separates a detector from a
 * hardcoded card.
 */
interface Detector
{
    /** Stable identifier, used in fingerprints and action invalidation. */
    public function key(): string;

    public function archetype(): string;

    public function area(): string;

    /** @return Signal[] */
    public function run(Metrics $metrics, Tenant $tenant): array;
}
