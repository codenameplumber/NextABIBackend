@props(['values' => [], 'height' => 34, 'color' => 'var(--c1)'])
@php
    /**
     * Sparkline: shape only. No axes, no ticks, no tooltip.
     *
     * Rendered server-side because a 40-point polyline does not need a
     * charting library, a canvas, or a round of client-side layout. The band
     * under the line is drawn at low opacity so a row of tiles reads as
     * texture rather than as ten competing charts.
     */
    $vals = array_values(array_filter($values, 'is_numeric'));
    $n = count($vals);
    $w = 120;
    $h = (float) $height;

    if ($n > 1) {
        $min = min($vals);
        $max = max($vals);
        $span = ($max - $min) ?: 1;
        $pts = [];
        foreach ($vals as $i => $v) {
            $x = round($i / ($n - 1) * $w, 2);
            $y = round($h - 2 - (($v - $min) / $span) * ($h - 4), 2);
            $pts[] = "$x,$y";
        }
        $line = implode(' ', $pts);
        $area = "0,{$h} ".$line." {$w},{$h}";
    }
@endphp
@if ($n > 1)
    <svg {{ $attributes }} viewBox="0 0 {{ $w }} {{ $height }}" preserveAspectRatio="none"
         width="100%" height="{{ $height }}" aria-hidden="true">
        <polygon points="{{ $area }}" fill="{{ $color }}" opacity=".12" />
        <polyline points="{{ $line }}" fill="none" stroke="{{ $color }}"
                  stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"
                  vector-effect="non-scaling-stroke" />
    </svg>
@endif
