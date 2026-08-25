@php
    use App\Support\Fmt;
    use App\Support\Vocab;

    $sortIcon = fn ($col) => $sort === $col ? ($dir === 'desc' ? '↓' : '↑') : '↕';
@endphp

<div class="stack">

    <x-page-head title="Properties"
                 :sub="Fmt::num($p['available']).' available · '.Fmt::moneyShort($p['inventoryValue']).' inventory value · median '.round($p['domMedian']).' days to sell'">
        <x-slot:actions>
            <a class="btn btn--ghost" href="{{ route('app.ask', ['q' => 'Revenue by location']) }}" wire:navigate>
                <x-icon name="ask" size="15" /><span>Ask about inventory</span>
            </a>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        What you have to sell and how fast it moves. Watch the **demand index**: lots of
        inquiries against very little stock means that segment is underpriced.
    </x-page-note>

    <div class="grid g-4">
        <x-kpi label="Available" :value="$p['available']" format="count" ico="property"
               :note="Fmt::num($p['reserved']).' reserved'" />

        <x-kpi label="Inventory value" :value="$p['inventoryValue']" format="money" ico="finance"
               help-key="inventory" :note="'avg '.Fmt::moneyShort($p['avgPrice'])" />

        <x-kpi label="Sell-through" :value="$p['sellThrough']" format="pct" ico="sales"
               help-key="occupancy" :note="Fmt::num($p['sold']).' sold all-time'" />

        <x-kpi label="Slow-moving" :value="$p['stale']" format="count" ico="clock" invert
               help-key="dom" :note="'past '.round($staleCut).' days on market'" />
    </div>

    <livewire:area-insights area="properties" />

    {{-- ------------------------------------------------ demand analysis -- --}}

    <div class="grid g-2">
        <x-card title="Demand index by segment"
                :sub="'Inquiries per available unit · portfolio norm '.number_format($p['baselineDemand'], 1)">
            <x-chart kind="hbar" :payload="$charts['demand']" />
            <x-chart-note>
                A tall bar is not a good thing on its own — it means people keep asking about
                something you barely have. That is either a pricing signal or an acquisition one.
            </x-chart-note>
        </x-card>

        <x-card title="Time on market by location" sub="Average days from listing to sale">
            <x-chart kind="bar" :payload="$charts['dom']" />
            <x-chart-note>
                Locations with fewer than three sales are excluded, because an average drawn from
                one sale is that sale, not an average.
            </x-chart-note>
        </x-card>
    </div>

    {{-- --------------------------------------------------------- table -- --}}

    <section class="card">
        <div class="card__head">
            <div>
                <div class="card__title">Portfolio</div>
                <div class="card__sub">
                    {{ Fmt::num($rows->total()) }} of {{ Fmt::num($p['total']) }} listings
                </div>
            </div>
        </div>

        <div class="toolbar">
            <input class="input" type="search" placeholder="Search ref or title…"
                   style="min-width:200px" wire:model.live.debounce.300ms="q">

            <select class="input" wire:model.live="location">
                <option value="">All locations</option>
                @foreach ($locations as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
            </select>

            <select class="input" wire:model.live="type">
                <option value="">All types</option>
                @foreach ($types as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
            </select>

            <select class="input" wire:model.live="status">
                <option value="">All statuses</option>
                @foreach (['available', 'reserved', 'sold', 'off_market'] as $s)
                    <option value="{{ $s }}">{{ Fmt::titleCase($s) }}</option>
                @endforeach
            </select>

            <button class="btn btn--sm btn--ghost {{ $filter === 'stale' ? 'is-on' : '' }}"
                    wire:click="toggleStale">
                <x-icon name="clock" size="13" /><span>Slow-moving</span>
            </button>

            <div class="spacer"></div>
        </div>

        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="is-sortable {{ $sort === 'title' ? 'is-sorted' : '' }}" wire:click="sortBy('title')">
                            <span>Property</span><span class="sort">{{ $sortIcon('title') }}</span>
                        </th>
                        <th class="is-sortable {{ $sort === 'type' ? 'is-sorted' : '' }}" wire:click="sortBy('type')">
                            <span>Type</span><span class="sort">{{ $sortIcon('type') }}</span>
                        </th>
                        <th class="is-sortable right {{ $sort === 'price' ? 'is-sorted' : '' }}" wire:click="sortBy('price')">
                            <span>Price</span><span class="sort">{{ $sortIcon('price') }}</span>
                        </th>
                        <th class="is-sortable {{ $sort === 'status' ? 'is-sorted' : '' }}" wire:click="sortBy('status')">
                            <span>Status</span><span class="sort">{{ $sortIcon('status') }}</span>
                        </th>
                        <th class="is-sortable right {{ $sort === 'inquiries' ? 'is-sorted' : '' }}" wire:click="sortBy('inquiries')">
                            <span>Inquiries</span><span class="sort">{{ $sortIcon('inquiries') }}</span>
                        </th>
                        <th class="is-sortable right {{ $sort === 'days_on_market' ? 'is-sorted' : '' }}"
                            wire:click="sortBy('days_on_market')">
                            <span>Days on market</span><span class="sort">{{ $sortIcon('days_on_market') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php $slow = $r->status === 'available' && $r->days_on_market > $staleCut; @endphp
                        <tr class="is-clickable {{ $slow ? 'is-flagged' : '' }}"
                            wire:key="prop-{{ $r->id }}" wire:click="open({{ $r->id }})">
                            <td>
                                <div class="cell-primary">{{ $r->title }}</div>
                                <div class="t-xs t-3">{{ $r->ref }} · {{ $r->location }}</div>
                            </td>
                            <td><span class="t-2">{{ $r->type }}</span></td>
                            <td class="right">
                                <div class="num">{{ Fmt::moneyShort($r->price) }}</div>
                                @if ($r->prior_price)
                                    <div class="t-xs t-3 num">was {{ Fmt::moneyShort($r->prior_price) }}</div>
                                @endif
                            </td>
                            <td><x-badge :kind="Vocab::statusKind($r->status)" dot>{{ Fmt::titleCase($r->status) }}</x-badge></td>
                            <td class="right"><span class="num">{{ Fmt::num($r->inquiries) }}</span></td>
                            <td class="right">
                                <span class="num {{ $slow ? 'neg semi' : 't-2' }}">{{ Fmt::num($r->days_on_market) }}d</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty title="Nothing here" ico="filter"
                                         msg="No properties match those filters.">
                                    <button class="btn btn--ghost" wire:click="clearFilters">Clear filters</button>
                                </x-empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="card__foot">{{ $rows->links() }}</div>
        @endif
    </section>

    {{-- -------------------------------------------------------- drawer -- --}}

    @if ($detail)
        <div class="scrim" wire:click.self="closeDrawer">
            <div class="drawer">
                <header class="overlay__head">
                    <h3>{{ $detail->title }}, {{ $detail->location }}</h3>
                    <button class="btn btn--sm btn--icon" wire:click="closeDrawer" aria-label="Close">
                        <x-icon name="x" size="15" />
                    </button>
                </header>

                <div class="overlay__body">
                    <div class="stack-4">
                        <div class="row-2 wrap">
                            <x-badge :kind="Vocab::statusKind($detail->status)" dot>
                                {{ Fmt::titleCase($detail->status) }}
                            </x-badge>
                            <x-badge>{{ $detail->ref }}</x-badge>
                            @if ($detail->promoted)<x-badge kind="ai">Promoted</x-badge>@endif
                            @if ($detail->repriced_at)<x-badge kind="ai">Repriced by NexABI</x-badge>@endif
                        </div>

                        <div class="grid g-2">
                            @foreach (array_filter([
                                'Price' => Fmt::money($detail->price),
                                'Type' => $detail->type,
                                'Bedrooms' => $detail->bedrooms ?: '—',
                                'Size' => Fmt::num($detail->size_sqm).' sqm',
                                'Listed' => Fmt::date($detail->listed_at),
                                'Days on market' => Fmt::num($detail->days_on_market).'d',
                                'Views' => Fmt::num($detail->views),
                                'Inquiries' => Fmt::num($detail->inquiries),
                                'Agent' => $detail->agent?->name ?? '—',
                                'Sold' => $detail->sold_at ? Fmt::date($detail->sold_at) : null,
                            ]) as $k => $v)
                                <div class="stat">
                                    <span class="stat__k">{{ $k }}</span>
                                    <span class="stat__v" style="font-size:var(--t-md)">{{ $v }}</span>
                                </div>
                            @endforeach
                        </div>

                        @if ($interested->isNotEmpty())
                            <div class="stack-2">
                                <div class="eyebrow">
                                    {{ $interested->count() }} open leads want this segment
                                </div>
                                <div class="card">
                                    @foreach ($interested as $l)
                                        <div class="log-item">
                                            <x-avatar :name="$l->name" size="sm" />
                                            <div class="grow">
                                                <div class="t-sm semi">{{ $l->name }}</div>
                                                <div class="t-xs t-3">
                                                    {{ Fmt::titleCase($l->status) }} ·
                                                    budget {{ Fmt::moneyShort($l->budget) }}
                                                </div>
                                            </div>
                                            <x-badge :kind="$l->score >= 70 ? 'pos' : ''">Score {{ $l->score }}</x-badge>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
