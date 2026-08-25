<?php

namespace App\Engine;

use App\Models\Tenant;

/**
 * What a detector emits.
 *
 * Every signal carries evidence, and every evidence item carries a drill
 * target. An insight you cannot click into is an insight nobody believes, so
 * the constructor refuses to build one without any.
 */
final class Signal
{
    public function __construct(
        public string $detector,
        public string $archetype,
        public string $area,
        /** Distinguishes per-entity signals from the same detector. */
        public string $entityKey,
        public string $title,
        public float $severity,
        public float $confidence,
        public array $facts = [],
        public array $evidence = [],
    ) {
        if ($evidence === []) {
            throw new \LogicException(
                "Detector [{$detector}] emitted a signal with no evidence. "
                .'Every figure has to be traceable to the rows behind it.'
            );
        }

        $this->severity = max(0.0, min(1.0, $severity));
        $this->confidence = max(0.0, min(1.0, $confidence));
    }

    /**
     * Stable identity across detector runs.
     *
     * Without this a dismissed finding resurrects on the next scheduled run
     * and the product becomes something people learn to ignore.
     */
    public function fingerprint(Tenant $tenant): string
    {
        return hash('sha256', $tenant->id.':'.$this->detector.':'.$this->entityKey);
    }

    /** critical | high | medium | low */
    public function band(): string
    {
        return match (true) {
            $this->severity >= 0.82 => 'critical',
            $this->severity >= 0.55 => 'high',
            $this->severity >= 0.28 => 'medium',
            default => 'low',
        };
    }

    public static function evidence(
        string $label,
        mixed $value,
        ?string $format = null,
        ?array $drill = null,
    ): array {
        return array_filter([
            'label' => $label,
            'value' => $value,
            'format' => $format,
            'drill' => $drill,
        ], fn ($v) => $v !== null);
    }
}
