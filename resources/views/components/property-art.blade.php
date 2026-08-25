@props(['property' => null])
@php
    /**
     * Building mark for the property card — generated, never a stock photo.
     *
     * A real-estate product that ships with someone else's photographs of
     * someone else's buildings is lying about what it knows. The glyph varies
     * with the unit's size, so a studio and a five-bed do not wear the same
     * picture.
     */
    $beds = $property?->bedrooms ?? 0;
    $glyph = $beds >= 4
        ? 'M3 21V8l9-5 9 5v13M3 21h18M9 21v-6h6v6M7 11h2M15 11h2M7 15h2'
        : ($beds > 0
            ? 'M4 21V9l8-5 8 5v12M4 21h16M9 21v-5h6v5M8 12h2M14 12h2'
            : 'M4 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18M16 8h3a2 2 0 0 1 2 2v12M8 6h2M8 10h2M8 14h2M8 18h2');
@endphp
<div class="prop-art">
    @if ($property?->ref)<span class="prop-art__ref">{{ $property->ref }}</span>@endif
    <span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="{{ $glyph }}"/>
        </svg>
    </span>
</div>
