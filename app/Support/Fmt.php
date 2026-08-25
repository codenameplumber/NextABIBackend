<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Formatting.
 *
 * BI lives or dies on number presentation. Everything here is tabular-safe and
 * currency defaults to NGN, because the product is aimed at Nigerian
 * real-estate operators and a dollar sign in the wrong place reads as someone
 * else's software.
 */
final class Fmt
{
    public const SYMBOL = '₦';

    /** Full currency, e.g. ₦12,450,000 */
    public static function money(?float $n, int $dp = 0): string
    {
        if ($n === null) {
            return '—';
        }

        return self::SYMBOL.number_format($n, $dp);
    }

    /** Compact currency for KPIs and axes, e.g. ₦12.4M, ₦1.8B */
    public static function moneyShort(?float $n): string
    {
        if ($n === null) {
            return '—';
        }

        $sign = $n < 0 ? '-' : '';
        $a = abs($n);

        return match (true) {
            $a >= 1e12 => $sign.self::SYMBOL.number_format($a / 1e12, 1).'T',
            $a >= 1e9 => $sign.self::SYMBOL.number_format($a / 1e9, 1).'B',
            $a >= 1e6 => $sign.self::SYMBOL.number_format($a / 1e6, 1).'M',
            $a >= 1e3 => $sign.self::SYMBOL.number_format($a / 1e3).'K',
            default => $sign.self::SYMBOL.number_format($a),
        };
    }

    /** Split for KPI display: ['value' => '₦1.8', 'unit' => 'B'] */
    public static function moneyParts(?float $n): array
    {
        $full = self::moneyShort($n);

        if (preg_match('/^(.*?)([KMBT])$/u', $full, $m)) {
            return ['value' => $m[1], 'unit' => $m[2]];
        }

        return ['value' => $full, 'unit' => ''];
    }

    public static function num(?float $n, int $dp = 0): string
    {
        return $n === null ? '—' : number_format($n, $dp);
    }

    public static function numShort(?float $n): string
    {
        if ($n === null) {
            return '—';
        }

        $a = abs($n);

        return match (true) {
            $a >= 1e9 => number_format($n / 1e9, 1).'B',
            $a >= 1e6 => number_format($n / 1e6, 1).'M',
            $a >= 1e4 => number_format($n / 1e3).'K',
            default => number_format($n),
        };
    }

    /** 0.284 -> "28.4%" */
    public static function pct(?float $n, int $dp = 1): string
    {
        if ($n === null || ! is_finite($n)) {
            return '—';
        }

        return number_format($n * 100, $dp).'%';
    }

    /** Percentage-point delta, always signed: "+12.4pts" */
    public static function pts(?float $n, int $dp = 1): string
    {
        if ($n === null || ! is_finite($n)) {
            return '—';
        }

        return ($n >= 0 ? '+' : '−').number_format(abs($n) * 100, $dp).'pts';
    }

    /** Signed relative change: 0.184 -> "+18.4%" */
    public static function delta(?float $n, int $dp = 1): string
    {
        if ($n === null || ! is_finite($n)) {
            return '—';
        }

        return ($n >= 0 ? '+' : '−').number_format(abs($n) * 100, $dp).'%';
    }

    /** "3.2×" — for multiples, used constantly by the detectors. */
    public static function mult(?float $n, int $dp = 1): string
    {
        if ($n === null || ! is_finite($n)) {
            return '—';
        }

        return str_replace('.0×', '×', number_format($n, $dp).'×');
    }

    public static function date(Carbon|string|null $ts): string
    {
        if (! $ts) {
            return '—';
        }

        return Carbon::parse($ts)->format('j M Y');
    }

    public static function dateShort(Carbon|string|null $ts): string
    {
        return $ts ? Carbon::parse($ts)->format('j M') : '—';
    }

    /** "12 days ago" / "in 3 days" */
    public static function ago(Carbon|string|null $ts): string
    {
        if (! $ts) {
            return '—';
        }

        $c = Carbon::parse($ts);
        $days = (int) $c->diffInDays(now(), absolute: true);
        $past = $c->isPast();

        if ($days === 0) {
            return 'today';
        }
        if ($days === 1) {
            return $past ? 'yesterday' : 'tomorrow';
        }

        $label = match (true) {
            $days < 31 => $days.' days',
            $days < 365 => intdiv($days, 30).' mo',
            default => number_format($days / 365, 1).' yr',
        };

        return $past ? $label.' ago' : 'in '.$label;
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(implode('', array_map(
            fn ($w) => mb_substr($w, 0, 1),
            array_slice($parts, 0, 2),
        )));
    }

    public static function titleCase(string $s): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $s));
    }

    /** Deterministic categorical slot (1-6) keyed by a string. */
    public static function hueOf(string $key): int
    {
        $h = 0;
        foreach (str_split($key) as $ch) {
            $h = ($h * 31 + ord($ch)) % 0xFFFFFFFF;
        }

        return ($h % 6) + 1;
    }

    /** Render an evidence value using the format the detector declared. */
    public static function evidence(array $item): string
    {
        $value = $item['value'] ?? null;

        return match ($item['format'] ?? null) {
            'money' => self::moneyShort((float) $value),
            'pct' => self::pct((float) $value, 1),
            default => is_numeric($value) ? self::num((float) $value) : (string) $value,
        };
    }

    /** Turn **bold** markers from the narrator into safe HTML. */
    public static function rich(?string $text): string
    {
        if (! $text) {
            return '';
        }

        // Whitespace is collapsed first: page notes are written across several
        // lines in the templates, so a bold run regularly straddles a newline.
        // Without this the asterisks render literally and the copy looks broken.
        return preg_replace(
            '/\*\*(.+?)\*\*/us',
            '<b>$1</b>',
            e(preg_replace('/\s+/u', ' ', trim($text))),
        );
    }
}
