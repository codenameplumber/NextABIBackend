<?php

namespace App\Engine;

/**
 * The statistics the detectors lean on.
 *
 * SQL does the grouping and aggregation; this does the maths over the small
 * series that comes back. Twelve to eighteen points is the normal input size,
 * so none of this needs to be clever — it needs to be correct, and it needs to
 * refuse to produce confident nonsense from thin samples.
 */
final class Stats
{
    /** Least-squares slope, per period. */
    public static function slope(array $values): float
    {
        $n = count($values);
        if ($n < 3) {
            return 0.0;
        }

        $mx = ($n - 1) / 2;
        $my = array_sum($values) / $n;

        $num = 0.0;
        $den = 0.0;
        foreach ($values as $i => $v) {
            $num += ($i - $mx) * ($v - $my);
            $den += ($i - $mx) ** 2;
        }

        return $den ? $num / $den : 0.0;
    }

    /** Slope as a fraction of the mean, so it is comparable across metrics. */
    public static function trendPct(array $values): float
    {
        if (! $values) {
            return 0.0;
        }
        $mean = array_sum($values) / count($values);

        return $mean ? self::slope($values) / abs($mean) : 0.0;
    }

    public static function zScore(float $value, array $series): float
    {
        if (count($series) < 4) {
            return 0.0;
        }

        $mean = array_sum($series) / count($series);
        $var = 0.0;
        foreach ($series as $v) {
            $var += ($v - $mean) ** 2;
        }
        $sd = sqrt($var / count($series));

        return $sd ? ($value - $mean) / $sd : 0.0;
    }

    public static function median(array $values): float
    {
        if (! $values) {
            return 0.0;
        }

        $s = array_values($values);
        sort($s);
        $m = intdiv(count($s), 2);

        return count($s) % 2 ? (float) $s[$m] : ($s[$m - 1] + $s[$m]) / 2;
    }

    /**
     * Laplace-smoothed rate.
     *
     * Without this, a campaign with fourteen leads and zero closes reports an
     * infinite conversion gap — or worse, a floor-clamped one, which looks
     * precise. Smoothing pulls small samples toward the prior so "50x worse"
     * stops appearing every time a denominator is thin.
     */
    public static function smoothRate(float $hits, float $total, float $prior = 0.06, float $weight = 12): float
    {
        return ($hits + $prior * $weight) / ($total + $weight);
    }

    /** Ratio between two rates, capped so a headline never overstates. */
    public static function ratio(float $a, float $b, float $cap = 8.0): float
    {
        return $b > 0 ? min($cap, $a / $b) : $cap;
    }

    /** Normalise "how far past the line are we" into 0..1. */
    public static function severity(float $ratio, float $soft = 1.0, float $hard = 3.0): float
    {
        return max(0.0, min(1.0, ($ratio - $soft) / max(0.0001, $hard - $soft)));
    }

    /** Confidence gated on sample size. Small n, low confidence, always. */
    public static function confidence(float $n, float $need = 30): float
    {
        return max(0.35, min(0.97, $n / $need));
    }

    public static function change(float $current, float $prior): float
    {
        if (! $prior) {
            return $current ? 1.0 : 0.0;
        }

        return ($current - $prior) / abs($prior);
    }

    public static function safeDiv(float $a, float $b): float
    {
        return $b ? $a / $b : 0.0;
    }

    /**
     * Three-point centred moving average.
     *
     * A weekly rate built on five to twelve leads swings between 0% and 100%
     * on sample noise alone. Fitting a slope straight through that measures
     * the noise, not the trend.
     */
    public static function smooth(array $values): array
    {
        $n = count($values);
        $out = [];

        for ($i = 0; $i < $n; $i++) {
            $lo = max(0, $i - 1);
            $hi = min($n, $i + 2);
            $win = array_slice($values, $lo, $hi - $lo);
            $out[] = array_sum($win) / count($win);
        }

        return $out;
    }

    /** Mean of the first or last k entries. */
    public static function edgeMean(array $values, int $k, bool $fromEnd = false): float
    {
        if (! $values) {
            return 0.0;
        }
        $slice = $fromEnd ? array_slice($values, -$k) : array_slice($values, 0, $k);

        return array_sum($slice) / max(1, count($slice));
    }
}
