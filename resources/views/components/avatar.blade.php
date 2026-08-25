@props(['name' => '', 'size' => ''])
@php
    use App\Support\Fmt;
    // Deterministic colour — the same person is the same colour on every page.
    $c = '--c'.Fmt::hueOf($name);
@endphp
<span {{ $attributes->merge([
        'class' => trim('avatar '.($size ? "avatar--{$size}" : '')),
        'title' => $name,
        'style' => "background: color-mix(in srgb, var($c) 20%, transparent); color: var($c);",
    ]) }}>{{ Fmt::initials($name) }}</span>
