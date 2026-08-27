@props([
    // 'full' — the glider and the nexABI wordmark together
    // 'mark' — the glider alone, for tight spaces and collapsed rails
    'variant' => 'full',
    'height' => 30,
    'alt' => 'NexABI — Adaptive Business Intelligence',
])
@php
    /**
     * One place the logo lives.
     *
     * It used to be an inline <svg> path repeated across ten templates, plus a
     * separate hand-built version in the email layout. Replacing a mark that
     * has been copied into ten files means finding ten files, and the one that
     * gets missed is always the email — because nobody looks at their own
     * transactional mail.
     *
     * Intrinsic width and height are written out so the browser reserves the
     * right box before the PNG lands. Without them the header reflows on every
     * cold load, which is the most visible layout shift a site can have.
     */
    $isMark = $variant === 'mark';
    $src = asset($isMark ? 'img/icons.png' : 'img/logo.png');

    // Real pixel dimensions of the two files, so the ratio is never guessed.
    [$nw, $nh] = $isMark ? [336, 395] : [784, 318];
    $width = (int) round($height * ($nw / $nh));
@endphp
<img {{ $attributes->merge(['class' => 'logo logo--'.$variant]) }}
     src="{{ $src }}"
     alt="{{ $alt }}"
     width="{{ $width }}"
     height="{{ $height }}"
     style="height:{{ $height }}px;width:auto;{{ $attributes->get('style') }}"
     decoding="async">
