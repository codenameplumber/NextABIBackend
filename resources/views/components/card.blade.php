@props(['title' => null, 'sub' => null, 'flush' => false])
<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <header class="card__head">
            <div>
                @if ($title)<div class="card__title">{{ $title }}</div>@endif
                @if ($sub)<div class="card__sub">{{ $sub }}</div>@endif
            </div>
            @isset($actions)<div class="row-2">{{ $actions }}</div>@endisset
        </header>
    @endif

    <div class="card__body {{ $flush ? 'card__body--flush' : '' }}">{{ $slot }}</div>

    @isset($foot)<footer class="card__foot">{{ $foot }}</footer>@endisset
</section>
