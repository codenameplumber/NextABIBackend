{{--
    The "what am I looking at" line. Every page gets one.

    Keep it to a sentence or two, say what the page is FOR, and point at the
    single thing on it most worth acting on. **Bold** markers are honoured.
--}}
<div {{ $attributes->merge(['class' => 'page-note']) }}>
    <x-icon name="info" size="15" />
    <span>{!! App\Support\Fmt::rich(trim($slot)) !!}</span>
</div>
