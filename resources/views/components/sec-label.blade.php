@props(['title', 'note' => null])
<div {{ $attributes->merge(['class' => 'sec-label']) }}>
    <span class="sec-label__t">{{ $title }}</span>
    <span class="sec-label__line"></span>
    @if ($note)<span class="sec-label__note">{{ $note }}</span>@endif
</div>
