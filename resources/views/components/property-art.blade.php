@props(['property' => null, 'eager' => false])
@php
    use App\Support\PropertyArt;
    use App\Support\PropertyPhotos;

    /**
     * The picture on a property card. Three sources, in order of truth.
     *
     *   1. properties.images  — the agency's own photograph of the actual unit
     *   2. PropertyPhotos     — a real photograph matched to the sale type
     *   3. PropertyArt        — a drawing, when there is no photograph at all
     *
     * The drawing stays because it is the only one of the three that can never
     * show the wrong building, and it is what a fresh install falls back to.
     */
    $art = PropertyArt::for($property);

    // The agency's own image may be a link it hosts elsewhere or a file
    // uploaded into public/. resolve() takes either and returns something a
    // browser can actually load from any URL on the site.
    $own = collect($property?->images ?? [])
        ->map(fn ($i) => PropertyPhotos::resolve(is_string($i) ? $i : ($i['path'] ?? null)))
        ->filter()
        ->first();

    $photo = $own ?: PropertyPhotos::for($property);
    $label = trim(($property?->bedrooms ? $property->bedrooms.'-bed ' : '').($property?->type ?? 'Property'));
    $where = $property?->location ? ' in '.$property->location : '';
@endphp

<div class="prop-art prop-art--{{ $art->scene() }}">
    @if ($photo)
        <img src="{{ $photo }}" alt="{{ $label }}{{ $where }}"
             class="prop-art__photo"
             loading="{{ $eager ? 'eager' : 'lazy' }}"
             decoding="{{ $eager ? 'sync' : 'async' }}">
    @else
        <svg viewBox="0 0 400 250" preserveAspectRatio="xMidYMax slice"
             role="img" aria-label="{{ $label }}{{ $where }}"
             class="prop-art__svg">
            {!! $art->svg() !!}
        </svg>
    @endif

    {{-- A scrim under the chips. Without it a white caption lands on a bright
         sky and a dark one lands on a shadow, and neither is readable. --}}
    <span class="prop-art__scrim" aria-hidden="true"></span>

    @if ($property?->ref)
        <span class="prop-art__ref">{{ $property->ref }}</span>
    @endif

    @if ($property?->type)
        <span class="prop-art__type">{{ $property->type }}</span>
    @endif
</div>
