@php use App\Support\Fmt; @endphp

<div class="sec-wrap">
    <div class="sec-h">
        <div>
            <h2>Properties</h2>
            <p>{{ Fmt::num($total) }} available · price and monthly figure shown on every one</p>
        </div>
        <a class="btn btn--ghost btn--sm" href="{{ route('enquire') }}" wire:navigate>
            <span>Cannot find it? Tell us what you want</span><x-icon name="right" size="13" />
        </a>
    </div>

    <div class="filters">
        <input type="search" class="input" style="min-width:220px;flex:1"
               placeholder="Search by name, area or reference"
               wire:model.live.debounce.400ms="search">

        <select class="input" wire:model.live="location" style="min-width:150px">
            <option value="">Anywhere</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc }}">{{ $loc }}</option>
            @endforeach
        </select>

        <select class="input" wire:model.live="type" style="min-width:140px">
            <option value="">Any type</option>
            @foreach ($types as $t)
                <option value="{{ $t }}">{{ $t }}</option>
            @endforeach
        </select>

        <select class="input" wire:model.live="maxPrice" style="min-width:150px">
            <option value="">Any price</option>
            <option value="25000000">Under ₦25M</option>
            <option value="50000000">Under ₦50M</option>
            <option value="100000000">Under ₦100M</option>
            <option value="250000000">Under ₦250M</option>
        </select>

        <select class="input" wire:model.live="sort" style="min-width:140px">
            <option value="newest">Newest first</option>
            <option value="cheapest">Price: low to high</option>
            <option value="dearest">Price: high to low</option>
        </select>

        @if ($search || $location || $type || $maxPrice)
            <button class="btn btn--ghost btn--sm" wire:click="clear">
                <x-icon name="x" size="13" /><span>Clear</span>
            </button>
        @endif
    </div>

    @if ($rows->isEmpty())
        <x-empty title="Nothing matches that"
                 msg="Try widening the filters, or tell us what you are looking for and we will let you know when something lands." />
        <div style="text-align:center;margin-top:var(--s-5)">
            <a class="btn btn--primary" href="{{ route('enquire') }}" wire:navigate>
                <span>Tell us what you want</span>
            </a>
        </div>
    @else
        <div class="pgrid">
            @foreach ($rows as $p)
                <a class="pcard" href="{{ route('property', $p->ref) }}" wire:navigate>
                    <div class="pcard__art">
                        <x-property-art :property="$p" />
                        @if ($p->promoted)
                            <span class="pcard__tag">Featured</span>
                        @endif
                    </div>
                    <div class="pcard__b">
                        <h3 class="pcard__t">{{ $p->title }}</h3>
                        <span class="pcard__loc">{{ $p->location }} · {{ $p->type }}</span>

                        <div class="pcard__meta">
                            @if ($p->bedrooms)<span>{{ $p->bedrooms }} bed</span>@endif
                            @if ($p->size_sqm)<span>{{ Fmt::num($p->size_sqm) }} m²</span>@endif
                        </div>

                        <span class="pcard__price">{{ Fmt::money($p->price) }}</span>

                        {{-- The monthly figure is the question every buyer is
                             actually asking, so it goes on the card. --}}
                        <span class="pcard__per">
                            From <b>{{ Fmt::money($p->instalmentFor(max($p->durationOptions()))) }}</b>
                            a month over {{ max($p->durationOptions()) }} months
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <div style="margin-top:var(--s-6)">{{ $rows->links() }}</div>
    @endif
</div>
