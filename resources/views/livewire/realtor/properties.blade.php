@php use App\Support\Fmt; @endphp

<div>
    <x-page-head title="Properties" :sub="$rows->total().' available to sell'" />

    <x-page-note>
        The same stock your clients see, with **one addition: what the sale is worth to you**.
        Rates are set per property, so it is worth checking before you decide what to push.
    </x-page-note>

    <div class="filters">
        <input type="search" class="input" style="min-width:220px;flex:1"
               placeholder="Search by name or reference"
               wire:model.live.debounce.400ms="search">

        <select class="input" wire:model.live="location" style="min-width:160px">
            <option value="">Anywhere</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc }}">{{ $loc }}</option>
            @endforeach
        </select>
    </div>

    @if ($rows->isEmpty())
        <x-empty title="Nothing matches that" msg="Try widening the filters." />
    @else
        <div class="pgrid">
            @foreach ($rows as $p)
                <div class="pcard">
                    <div class="pcard__art"><x-property-art :property="$p" /></div>
                    <div class="pcard__b">
                        <h3 class="pcard__t">{{ $p->title }}</h3>
                        <span class="pcard__loc">{{ $p->location }} · {{ $p->bedrooms }} bed {{ $p->type }}</span>
                        <span class="pcard__price">{{ Fmt::money($p->price) }}</span>

                        <span class="pcard__per">
                            Your commission
                            <b>{{ Fmt::money($p->commissionOn()) }}</b>
                            at {{ number_format($p->commissionPct(), 1) }}%
                        </span>

                        <div class="row-2" style="gap:var(--s-2);margin-top:var(--s-2)">
                            <a class="btn btn--ghost btn--sm" href="{{ route('property', $p->ref) }}">
                                <span>View</span>
                            </a>
                            <button class="btn btn--sm" data-copy="{{ route('property', $p->ref) }}?ref={{ $realtor->code }}">
                                <x-icon name="plus" size="13" /><span>Copy my link</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top:var(--s-6)">{{ $rows->links() }}</div>
    @endif
</div>
