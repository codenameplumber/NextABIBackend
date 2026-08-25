@props([
    'label',
    'value' => 0,
    'format' => 'number',
    'delta' => null,
    'deltaMode' => 'pct',
    'invert' => false,
    'spark' => [],
    'sparkColor' => 'var(--c1)',
    'note' => null,
    'compare' => null,
    'ico' => null,
    'helpKey' => null,
    'help' => null,
    'href' => null,
])
@php
    /**
     * KPI tile.
     *
     * A stat tile is a chart form in its own right — when the data's job is
     * "one headline number", a plot is the wrong answer.
     */
    use App\Support\Fmt;
    use App\Support\MetricHelp;

    $v = (float) $value;

    [$main, $unit] = match ($format) {
        'money' => array_values(Fmt::moneyParts($v)),
        'moneyFull' => [Fmt::moneyShort($v), ''],
        'number' => [Fmt::numShort($v), ''],
        'count' => [Fmt::num($v), ''],
        'pct' => [Fmt::pct($v, 1), ''],
        'x' => [number_format($v, 2), '×'],
        'days' => [(string) round($v), 'd'],
        default => [(string) $value, ''],
    };

    $tip = $help ?? MetricHelp::get($helpKey);
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'kpi '.($href ? 'kpi--clickable' : '')]) }}>

    <div class="kpi__label">
        @if ($ico)<x-icon :name="$ico" size="13" />@endif
        <span>{{ $label }}</span>
        @if ($tip)
            <span class="kpi__help" title="{{ $tip }}" aria-label="{{ $tip }}" tabindex="0">?</span>
        @endif
    </div>

    <div class="kpi__value">{{ $main }}@if($unit)<small>{{ $unit }}</small>@endif</div>

    @if ($delta !== null || $note || $compare)
        <div class="kpi__foot">
            @if ($delta !== null)
                <x-delta :value="$delta" :mode="$deltaMode" :invert="$invert" />
            @endif
            @if ($compare)<span class="t-sm t-4">{{ $compare }}</span>@endif
            @if ($note)<span class="t-sm t-3">{{ $note }}</span>@endif
        </div>
    @endif

    @if (count($spark) > 2)
        <div class="kpi__spark"><x-spark :values="$spark" :color="$sparkColor" /></div>
    @endif
</{{ $tag }}>
