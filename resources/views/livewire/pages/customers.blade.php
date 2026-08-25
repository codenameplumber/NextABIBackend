@php
    use App\Support\Fmt;

    $sortIcon = fn ($col) => $sort === $col ? ($dir === 'desc' ? '↓' : '↑') : '↕';
    $segKind = fn ($s) => $s === 'vip' ? 'ai' : ($s === 'high_value' ? 'pos' : '');
@endphp

<div class="stack">

    <x-page-head title="Customers"
                 :sub="Fmt::num($c['total']).' customers · '.Fmt::moneyShort($c['ltv']).' lifetime value · '.number_format($c['satisfaction'], 2).' average satisfaction'">
        <x-slot:actions>
            <a class="btn btn--ghost"
               href="{{ route('app.ask', ['q' => 'How many customers do we have?']) }}" wire:navigate>
                <x-icon name="ask" size="15" /><span>Ask</span>
            </a>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        Everyone who has bought from you, sorted by lifetime value. Satisfaction sits alongside
        so you can see **who is at risk of not coming back**.
    </x-page-note>

    <div class="grid g-4">
        <x-kpi label="Customers" :value="$c['total']" format="count" ico="customers"
               :note="Fmt::num($c['repeat']).' repeat buyers'"
               :spark="array_column($c['newSeries'], 'value')" />

        <x-kpi label="Lifetime value" :value="$c['ltv']" format="money" ico="finance"
               :note="'avg '.Fmt::moneyShort($c['avgLtv'])" />

        <x-kpi label="Satisfaction" :value="$c['satisfaction']" format="x" ico="feedback"
               help-key="satisfaction" :note="Fmt::num($c['promoters']).' promoters'" />

        <x-kpi label="Detractors" :value="$c['detractors']" format="count" ico="warn" invert
               note="rated 2.5 or below" />
    </div>

    <livewire:area-insights area="customers" />

    <div class="grid g-2">
        <x-card title="Value by segment" sub="Lifetime value concentration">
            <x-chart kind="donut" :payload="$charts['segments']" />
        </x-card>

        <x-card title="Top customers" sub="By lifetime value">
            <x-chart kind="hbar" :payload="$charts['top']" />
            <x-chart-note>
                When a handful of names carry most of the value, losing one of them is not a bad
                quarter — it is a different business.
            </x-chart-note>
        </x-card>
    </div>

    {{-- ---------------------------------------------------------- table -- --}}

    <section class="card">
        <div class="card__head">
            <div>
                <div class="card__title">All customers</div>
                <div class="card__sub">{{ Fmt::num($rows->total()) }} of {{ Fmt::num($c['total']) }}</div>
            </div>
        </div>

        <div class="toolbar">
            <input class="input" type="search" placeholder="Search customer…"
                   style="min-width:200px" wire:model.live.debounce.300ms="q">

            <select class="input" wire:model.live="segment">
                <option value="">All segments</option>
                @foreach ($c['bySegment'] as $s)
                    <option value="{{ $s['key'] }}">{{ Fmt::titleCase($s['key']) }}</option>
                @endforeach
            </select>

            <button class="btn btn--sm btn--ghost {{ $filter === 'detractors' ? 'is-on' : '' }}"
                    wire:click="toggleDetractors">
                <x-icon name="warn" size="13" /><span>Detractors only</span>
            </button>

            <div class="spacer"></div>
        </div>

        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="is-sortable {{ $sort === 'name' ? 'is-sorted' : '' }}" wire:click="sortBy('name')">
                            <span>Customer</span><span class="sort">{{ $sortIcon('name') }}</span>
                        </th>
                        <th class="is-sortable {{ $sort === 'segment' ? 'is-sorted' : '' }}" wire:click="sortBy('segment')">
                            <span>Segment</span><span class="sort">{{ $sortIcon('segment') }}</span>
                        </th>
                        <th class="is-sortable right {{ $sort === 'ltv' ? 'is-sorted' : '' }}" wire:click="sortBy('ltv')">
                            <span>Lifetime value</span><span class="sort">{{ $sortIcon('ltv') }}</span>
                        </th>
                        <th class="right">Deals</th>
                        <th class="is-sortable right {{ $sort === 'satisfaction' ? 'is-sorted' : '' }}"
                            wire:click="sortBy('satisfaction')">
                            <span>Satisfaction</span><span class="sort">{{ $sortIcon('satisfaction') }}</span>
                        </th>
                        <th class="is-sortable right {{ $sort === 'since' ? 'is-sorted' : '' }}" wire:click="sortBy('since')">
                            <span>Customer since</span><span class="sort">{{ $sortIcon('since') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php $detractor = $r->satisfaction !== null && $r->satisfaction <= 2.5; @endphp
                        <tr class="is-clickable {{ $detractor ? 'is-flagged' : '' }}"
                            wire:key="cust-{{ $r->id }}" wire:click="open({{ $r->id }})">
                            <td>
                                <div class="row-2">
                                    <x-avatar :name="$r->name" size="sm" />
                                    <div>
                                        <div class="cell-primary">{{ $r->name }}</div>
                                        <div class="t-xs t-3">{{ $r->email }}</div>
                                    </div>
                                    @if ($r->escalated)<x-badge kind="ai">Escalated</x-badge>@endif
                                    @if ($r->recovery_status === 'open')<x-badge kind="ai">Recovery open</x-badge>@endif
                                </div>
                            </td>
                            <td><x-badge :kind="$segKind($r->segment)">{{ Fmt::titleCase($r->segment) }}</x-badge></td>
                            <td class="right"><span class="num semi">{{ Fmt::moneyShort($r->ltv) }}</span></td>
                            <td class="right"><span class="num">{{ Fmt::num($r->won_deals) }}</span></td>
                            <td class="right">
                                @if ($r->satisfaction === null)
                                    <span class="t-4">no feedback</span>
                                @else
                                    <div class="row-2" style="justify-content:flex-end">
                                        <span class="num {{ $detractor ? 'neg semi' : ($r->satisfaction >= 4.5 ? 'pos' : '') }}">
                                            {{ number_format($r->satisfaction, 1) }}
                                        </span>
                                        <span class="meter">
                                            <i style="width:{{ max(2, min(100, $r->satisfaction / 5 * 100)) }}%;
                                                      background:var({{ $detractor ? '--neg' : '--pos' }})"></i>
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="right"><span class="num t-3">{{ Fmt::date($r->since) }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty title="Nothing here" ico="filter"
                                         msg="No customers match those filters.">
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

    {{-- --------------------------------------------------------- drawer -- --}}

    @if ($detail)
        <div class="scrim" wire:click.self="closeDrawer">
            <div class="drawer">
                <header class="overlay__head">
                    <h3>{{ $detail->name }}</h3>
                    <button class="btn btn--sm btn--icon" wire:click="closeDrawer" aria-label="Close">
                        <x-icon name="x" size="15" />
                    </button>
                </header>

                <div class="overlay__body">
                    <div class="stack-4">
                        <div class="row-2 wrap">
                            <x-badge :kind="$segKind($detail->segment)">{{ Fmt::titleCase($detail->segment) }}</x-badge>
                            @if ($detail->rank)<x-badge>#{{ $detail->rank }} by value</x-badge>@endif
                            @if ($overdue)<x-badge kind="neg" dot>{{ $overdue }} overdue</x-badge>@endif
                        </div>

                        <div class="grid g-2">
                            @foreach ([
                                'Lifetime value' => Fmt::moneyShort($detail->ltv),
                                'Deals' => Fmt::num($detail->deals->where('won', true)->count()),
                                'Customer since' => Fmt::date($detail->since),
                                'Satisfaction' => $detail->satisfaction ? number_format($detail->satisfaction, 2) : '—',
                                'Source' => $detail->source,
                                'Agent' => $detail->agent?->name ?? '—',
                            ] as $k => $v)
                                <div class="stat">
                                    <span class="stat__k">{{ $k }}</span>
                                    <span class="stat__v" style="font-size:var(--t-md)">{{ $v }}</span>
                                </div>
                            @endforeach
                        </div>

                        @if ($detail->deals->isNotEmpty())
                            <div class="stack-2">
                                <div class="eyebrow">Deals</div>
                                <div class="card">
                                    @foreach ($detail->deals->where('won', true) as $d)
                                        <div class="log-item">
                                            <div class="log-item__dot"><x-icon name="check" size="13" /></div>
                                            <div class="grow">
                                                <div class="t-sm semi">{{ $d->property->title ?? $d->property_type }}</div>
                                                <div class="t-xs t-3">
                                                    {{ $d->location }} · closed {{ Fmt::date($d->closed_at) }}
                                                </div>
                                            </div>
                                            <span class="num semi">{{ Fmt::moneyShort($d->value) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($detail->feedback->isNotEmpty())
                            <div class="stack-2">
                                <div class="eyebrow">Feedback · {{ $detail->feedback->count() }}</div>
                                @foreach ($detail->feedback as $f)
                                    <div class="card" style="padding:var(--s-4)">
                                        <div class="row-2" style="margin-bottom:6px">
                                            <span class="semi">
                                                {{ str_repeat('★', $f->rating).str_repeat('☆', 5 - $f->rating) }}
                                            </span>
                                            <span class="t-xs t-3">{{ $f->channel }} · {{ Fmt::date($f->created_at) }}</span>
                                        </div>
                                        <div class="quote">{{ $f->text }}</div>
                                        <div class="row-2" style="margin-top:8px">
                                            @foreach ($f->tags ?? [] as $t)<x-badge>{{ $t }}</x-badge>@endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
