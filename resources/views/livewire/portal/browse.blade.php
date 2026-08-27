@php use App\Support\Fmt; @endphp

<div>
    <div class="pt-sec">
        <div>
            <h2>Browse properties</h2>
            <p>{{ $rows->total() }} available · choose your own payment term</p>
        </div>
    </div>

    <div class="page-note pt-note">
        <x-icon name="info" size="15" />
        <span>
            Committing holds the unit while we confirm the details. Nothing is binding until it
            is approved and you have seen the full schedule.
        </span>
    </div>

    <div class="filters" style="margin-top:var(--s-5)">
        <input type="search" class="input" style="min-width:200px;flex:1"
               placeholder="Search by name or area"
               wire:model.live.debounce.400ms="search">

        <select class="input" wire:model.live="location" style="min-width:150px">
            <option value="">Anywhere</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc }}">{{ $loc }}</option>
            @endforeach
        </select>

        <select class="input" wire:model.live="maxPrice" style="min-width:150px">
            <option value="">Any price</option>
            <option value="25000000">Under ₦25M</option>
            <option value="50000000">Under ₦50M</option>
            <option value="100000000">Under ₦100M</option>
            <option value="250000000">Under ₦250M</option>
        </select>
    </div>

    @if ($rows->isEmpty())
        <x-empty title="Nothing matches that" msg="Try widening the filters." />
    @else
        <div class="pgrid">
            @foreach ($rows as $p)
                <div class="pcard">
                    <div class="pcard__art">
                        <x-property-art :property="$p" />
                        @if (in_array($p->id, $held, true))
                            <span class="pcard__tag">You hold this</span>
                        @endif
                    </div>
                    <div class="pcard__b">
                        <h3 class="pcard__t">{{ $p->title }}</h3>
                        <span class="pcard__loc">{{ $p->location }} · {{ $p->bedrooms }} bed {{ $p->type }}</span>
                        <span class="pcard__price">{{ Fmt::money($p->price) }}</span>

                        <span class="pcard__per">
                            From <b>{{ Fmt::money($p->instalmentFor(max($p->durationOptions()))) }}</b>
                            a month over {{ max($p->durationOptions()) }} months
                        </span>

                        <div class="row-2" style="gap:var(--s-2);margin-top:var(--s-2)">
                            @if (in_array($p->id, $held, true))
                                <span class="btn btn--ghost btn--sm" style="pointer-events:none;opacity:.6">
                                    <span>Already committed</span>
                                </span>
                            @else
                                <a class="btn btn--primary btn--sm"
                                   href="{{ route('portal.commit', $p->ref) }}" wire:navigate>
                                    <span>Commit</span>
                                </a>
                            @endif
                            <a class="btn btn--ghost btn--sm" href="{{ route('property', $p->ref) }}">
                                <span>Details</span>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top:var(--s-6)">{{ $rows->links() }}</div>
    @endif
</div>
