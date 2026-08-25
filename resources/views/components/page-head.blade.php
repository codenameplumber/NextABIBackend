@props(['title', 'sub' => null])
<div {{ $attributes->merge(['class' => 'page-head']) }}>
    <div class="page-head__title">
        <h1>{{ $title }}</h1>
        @if ($sub)<p>{{ $sub }}</p>@endif
    </div>
    @isset($actions)<div class="row-2 wrap">{{ $actions }}</div>@endisset
</div>
