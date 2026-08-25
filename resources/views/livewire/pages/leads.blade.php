@php
    use App\Support\Fmt;

    $sortIcon = fn ($col) => $sort === $col ? ($dir === 'desc' ? '↓' : '↑') : '↕';
@endphp

<div class="stack">

    <x-page-head title="Leads"
                 :sub="Fmt::num($leads['open']).' open · '.Fmt::moneyShort($leads['pipeline']).' pipeline · '.Fmt::num($leads['stale']).' need follow-up'">
        <x-slot:actions>
            @if ($leads['stale'] > 0)
                <button class="btn btn--primary" wire:click="sweepStale"
                        wire:loading.attr="disabled" wire:target="sweepStale">
                    <x-icon name="bolt" size="15" />
                    <span wire:loading.remove wire:target="sweepStale">
                        Follow up {{ Fmt::num($leads['stale']) }} stale leads
                    </span>
                    <span wire:loading wire:target="sweepStale">Scheduling…</span>
                </button>
            @endif
            <a class="btn btn--ghost"
               href="{{ route('app.ask', ['q' => 'Which lead sources convert best?']) }}" wire:navigate>
                <x-icon name="ask" size="15" /><span>Ask about leads</span>
            </a>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        Everyone who has enquired. Rows highlighted in red have had **no contact for 14 days
        or more** — those are the ones quietly costing you money.
    </x-page-note>

    <div class="grid g-4">
        <x-kpi label="Open leads" :value="$leads['open']" format="count" ico="leads"
               :note="Fmt::num($leads['hot']).' scoring 70+'"
               :spark="array_column($leads['series'], 'value')" />

        <x-kpi label="Pipeline value" :value="$leads['pipeline']" format="money" ico="sales"
               help-key="pipeline" :note="'avg score '.round($leads['avgScore'])" />

        <x-kpi label="New this period" :value="$leads['new']" format="count"
               :delta="$leads['newChange']" ico="plus" />

        <x-kpi label="Stale · 14+ days" :value="$leads['stale']" format="count" ico="clock"
               help-key="stale" :note="Fmt::moneyShort($leads['stalePipeline']).' at risk'"
               :compare="'healthy is about '.$leads['staleBaseline']" />
    </div>

    <livewire:area-insights area="leads" />

    {{-- ------------------------------------------------------- analysis -- --}}

    <div class="grid g-2">
        <x-card title="Conversion by source" sub="Closed rate across the full lead history">
            <x-chart kind="hbar" :payload="$charts['conversion']" />
            <x-chart-note>
                A source can send plenty of leads and still be the wrong one to spend on.
                Read this next to the volume, not instead of it.
            </x-chart-note>
        </x-card>

        <x-card title="Funnel" sub="Where leads are being lost">
            <x-chart kind="funnel" :payload="$charts['funnel']" />
            <x-chart-note>
                Counted on the deepest stage each lead ever reached, so a lead that was lost
                after a viewing still counts as having viewed.
            </x-chart-note>
        </x-card>
    </div>

    {{-- ---------------------------------------------------------- table -- --}}

    <section class="card">
        <div class="card__head">
            <div>
                <div class="card__title">All leads</div>
                <div class="card__sub">
                    {{ Fmt::num($rows->total()) }} of {{ Fmt::num($leads['total']) }} records
                    @if ($filter === 'stale') · rows highlighted red have gone 14+ days without contact @endif
                </div>
            </div>
        </div>

        <div class="toolbar">
            <input class="input" type="search" placeholder="Search by name…"
                   style="min-width:200px" wire:model.live.debounce.300ms="q">

            <select class="input" wire:model.live="status">
                <option value="">All statuses</option>
                @foreach ($stages as $s)
                    <option value="{{ $s }}">{{ Fmt::titleCase($s) }}</option>
                @endforeach
            </select>

            <select class="input" wire:model.live="source">
                <option value="">All sources</option>
                @foreach ($sources as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>

            <button class="btn btn--sm btn--ghost {{ $filter === 'stale' ? 'is-on' : '' }}"
                    wire:click="toggleFilter('stale')">
                <x-icon name="clock" size="13" /><span>Stale only</span>
            </button>

            <button class="btn btn--sm btn--ghost {{ $filter === 'hot-idle' ? 'is-on' : '' }}"
                    wire:click="toggleFilter('hot-idle')">
                <x-icon name="bolt" size="13" /><span>Score 70+</span>
            </button>

            <div class="spacer"></div>
        </div>

        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="is-sortable {{ $sort === 'name' ? 'is-sorted' : '' }}" wire:click="sortBy('name')">
                            <span>Lead</span><span class="sort">{{ $sortIcon('name') }}</span>
                        </th>
                        <th class="is-sortable right {{ $sort === 'score' ? 'is-sorted' : '' }}" wire:click="sortBy('score')">
                            <span>Score</span><span class="sort">{{ $sortIcon('score') }}</span>
                        </th>
                        <th class="is-sortable {{ $sort === 'status' ? 'is-sorted' : '' }}" wire:click="sortBy('status')">
                            <span>Status</span><span class="sort">{{ $sortIcon('status') }}</span>
                        </th>
                        <th class="is-sortable {{ $sort === 'source' ? 'is-sorted' : '' }}" wire:click="sortBy('source')">
                            <span>Source</span><span class="sort">{{ $sortIcon('source') }}</span>
                        </th>
                        <th class="is-sortable right {{ $sort === 'budget' ? 'is-sorted' : '' }}" wire:click="sortBy('budget')">
                            <span>Budget</span><span class="sort">{{ $sortIcon('budget') }}</span>
                        </th>
                        <th>Agent</th>
                        <th class="is-sortable right {{ $sort === 'last_contact_at' ? 'is-sorted' : '' }}"
                            wire:click="sortBy('last_contact_at')">
                            <span>Last contact</span><span class="sort">{{ $sortIcon('last_contact_at') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php $stale = $r->isStale(); @endphp
                        <tr class="is-clickable {{ $stale ? 'is-flagged' : '' }}" wire:key="lead-{{ $r->id }}"
                            wire:click="open({{ $r->id }})">
                            <td>
                                <div class="row-2">
                                    <x-avatar :name="$r->name" size="sm" />
                                    <div>
                                        <div class="cell-primary">{{ $r->name }}</div>
                                        <div class="t-xs t-3">{{ $r->wants_type }} · {{ $r->wants_location }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="right">
                                <div class="row-2" style="justify-content:flex-end">
                                    <span class="num semi">{{ $r->score }}</span>
                                    <span class="meter">
                                        <i style="width:{{ max(2, min(100, $r->score)) }}%;
                                                  background:var({{ $r->score >= 70 ? '--pos' : ($r->score >= 45 ? '--c1' : '--c-other') }})"></i>
                                    </span>
                                </div>
                            </td>
                            <td><x-badge :kind="App\Support\Vocab::statusKind($r->status)" dot>{{ Fmt::titleCase($r->status) }}</x-badge></td>
                            <td><span class="t-2">{{ $r->source }}</span></td>
                            <td class="right"><span class="num">{{ Fmt::moneyShort($r->budget) }}</span></td>
                            <td>
                                @if ($r->agent)
                                    <div class="row-2">
                                        <x-avatar :name="$r->agent->name" size="sm" />
                                        <span class="t-2 truncate">{{ $r->agent->name }}</span>
                                    </div>
                                @else — @endif
                            </td>
                            <td class="right">
                                <span class="num {{ $stale ? 'neg semi' : 't-3' }}"
                                      title="{{ Fmt::date($r->last_contact_at) }}">
                                    {{ Fmt::ago($r->last_contact_at) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty title="Nothing here" ico="filter"
                                         msg="No leads match those filters.">
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
                            <x-badge :kind="App\Support\Vocab::statusKind($detail->status)" dot>
                                {{ Fmt::titleCase($detail->status) }}
                            </x-badge>
                            <x-badge :kind="$detail->score >= 70 ? 'pos' : ''">Score {{ $detail->score }}</x-badge>
                            @if ($detail->isStale())
                                <x-badge kind="neg" dot>{{ $detail->daysSinceContact() }} days idle</x-badge>
                            @endif
                        </div>

                        <div class="grid g-2">
                            @foreach ([
                                'Budget' => Fmt::moneyShort($detail->budget),
                                'Source' => $detail->source,
                                'Wants' => $detail->wants_type,
                                'Location' => $detail->wants_location,
                                'Agent' => $detail->agent?->name ?? '—',
                                'Created' => Fmt::date($detail->created_at),
                                'Last contact' => Fmt::ago($detail->last_contact_at),
                                'Email' => $detail->email,
                            ] as $k => $v)
                                <div class="stat">
                                    <span class="stat__k">{{ $k }}</span>
                                    <span class="stat__v" style="font-size:var(--t-md)">{{ $v }}</span>
                                </div>
                            @endforeach
                        </div>

                        @if ($detail->is_open)
                            <div class="row-2">
                                <button class="btn btn--primary" wire:click="followUp({{ $detail->id }})">
                                    <x-icon name="bolt" size="15" /><span>Schedule follow-up</span>
                                </button>
                            </div>
                        @endif

                        <div class="stack-2">
                            <div class="eyebrow">Activity · {{ $timeline->count() }} events</div>
                            <div class="card" style="max-height:340px;overflow-y:auto">
                                @forelse ($timeline as $a)
                                    <div class="log-item">
                                        <div class="log-item__dot" style="background:var(--surface-3);color:var(--text-2)">
                                            <x-icon :name="App\Support\Vocab::activityIcon($a->type)" size="13" />
                                        </div>
                                        <div class="grow">
                                            <div class="t-sm semi">{{ Fmt::titleCase($a->type) }}</div>
                                            @if ($a->note)<div class="t-xs t-3">{{ $a->note }}</div>@endif
                                        </div>
                                        <span class="t-xs t-4 nowrap">{{ Fmt::date($a->at) }}</span>
                                    </div>
                                @empty
                                    <div class="card__body t-sm t-3">Nothing recorded against this lead yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
