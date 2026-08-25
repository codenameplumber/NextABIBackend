@props(['value' => null, 'mode' => 'pct', 'invert' => false, 'suffix' => ''])
@php
    /**
     * Signed change chip.
     *
     * `invert` is for metrics where down is good — days on market, cost per
     * acquisition, overdue balance. Without it a falling CAC would render red.
     */
    use App\Support\Fmt;

    $v = is_numeric($value) ? (float) $value : null;
    $finite = $v !== null && is_finite($v);
    $flat = $finite && abs($v) < ($mode === 'pts' ? 0.002 : 0.005);
    $good = $invert ? $v < 0 : $v > 0;
    $cls = ! $finite ? 'flat' : ($flat ? 'flat' : ($good ? 'up' : 'down'));

    $text = ! $finite ? '—' : match ($mode) {
        'pts' => Fmt::pts($v),
        'raw' => ($v >= 0 ? '+' : '−').number_format(abs($v)).$suffix,
        default => Fmt::delta($v),
    };
@endphp
<span {{ $attributes->merge(['class' => "delta delta--{$cls}"]) }}>
    @if ($finite && ! $flat)<x-icon :name="$v > 0 ? 'up' : 'down'" size="11" />@endif
    <span>{{ $text }}</span>
</span>
