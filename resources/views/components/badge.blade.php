@props(['kind' => '', 'dot' => false])
<span {{ $attributes->merge(['class' => trim('badge '.($kind ? "badge--{$kind}" : '').($dot ? ' badge--dot' : ''))]) }}>
    <span>{{ $slot }}</span>
</span>
