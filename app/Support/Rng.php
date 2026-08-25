<?php

namespace App\Support;

/**
 * Seeded PRNG (mulberry32) plus the sampling helpers the simulator needs.
 *
 * Every dataset NexABI generates is byte-identical across runs and machines.
 * That matters more than it sounds: the demo never surprises you, screenshots
 * stay valid, and a bug is always reproducible.
 */
final class Rng
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed & 0xFFFFFFFF;
    }

    /**
     * Float in [0, 1). Straight mulberry32.
     *
     * PHP has no unsigned 32-bit type and >> is arithmetic, so every step masks
     * back to 32 bits explicitly. Getting this subtly wrong yields a generator
     * that still looks random but is not reproducible, which is the one
     * property the whole demo depends on.
     */
    public function next(): float
    {
        $this->state = ($this->state + 0x6D2B79F5) & 0xFFFFFFFF;

        $t = $this->state;
        $t = self::imul($t ^ self::ushr($t, 15), 1 | $t);
        $t = (($t + self::imul($t ^ self::ushr($t, 7), 61 | $t)) & 0xFFFFFFFF) ^ $t;
        $t = ($t ^ self::ushr($t, 14)) & 0xFFFFFFFF;

        return $t / 4294967296;
    }

    /** Logical right shift, matching JavaScript's >>> operator. */
    private static function ushr(int $v, int $bits): int
    {
        return ($v & 0xFFFFFFFF) >> $bits;
    }

    /** 32-bit integer multiply, matching JavaScript's Math.imul. */
    private static function imul(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;

        $ah = self::ushr($a, 16);
        $al = $a & 0xFFFF;
        $bh = self::ushr($b, 16);
        $bl = $b & 0xFFFF;

        return (($al * $bl) + ((($ah * $bl + $al * $bh) & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }

    public function float(float $lo, float $hi): float
    {
        return $lo + $this->next() * ($hi - $lo);
    }

    public function int(int $lo, int $hi): int
    {
        return (int) floor($lo + $this->next() * ($hi - $lo + 1));
    }

    public function chance(float $p): bool
    {
        return $this->next() < $p;
    }

    public function pick(array $arr): mixed
    {
        return $arr[(int) floor($this->next() * count($arr))];
    }

    /** Weighted pick. $weights runs parallel to $arr. */
    public function weighted(array $arr, array $weights): mixed
    {
        $total = array_sum($weights);
        $r = $this->next() * $total;
        foreach ($arr as $i => $item) {
            $r -= $weights[$i];
            if ($r <= 0) {
                return $item;
            }
        }

        return end($arr);
    }

    /** Pick from a list of ['v' => mixed, 'w' => float] pairs. */
    public function fromTable(array $table): mixed
    {
        return $this->weighted(
            array_column($table, 'v'),
            array_column($table, 'w'),
        );
    }

    /** $n distinct items. */
    public function sample(array $arr, int $n): array
    {
        $pool = array_values($arr);
        $out = [];
        for ($i = 0; $i < $n && count($pool) > 0; $i++) {
            $k = (int) floor($this->next() * count($pool));
            $out[] = $pool[$k];
            array_splice($pool, $k, 1);
        }

        return $out;
    }

    public function shuffle(array $arr): array
    {
        $a = array_values($arr);
        for ($i = count($a) - 1; $i > 0; $i--) {
            $j = (int) floor($this->next() * ($i + 1));
            [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
        }

        return $a;
    }

    /** Box-Muller normal. Business data is rarely uniform. */
    public function normal(float $mean = 0, float $sd = 1): float
    {
        $u = max($this->next(), 1e-9);
        $v = $this->next();

        return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    /** Clamped normal — the workhorse for prices, scores and durations. */
    public function gauss(float $mean, float $sd, float $lo = -INF, float $hi = INF): float
    {
        return min($hi, max($lo, $this->normal($mean, $sd)));
    }

    /** Right-skewed — deal values, days on market, response times. */
    public function logNormal(float $median, float $sigma = 0.5): float
    {
        return $median * exp($this->normal(0, $sigma));
    }

    /** Timestamp uniformly between two unix seconds. */
    public function between(int $a, int $b): int
    {
        return (int) floor($a + $this->next() * ($b - $a));
    }
}
