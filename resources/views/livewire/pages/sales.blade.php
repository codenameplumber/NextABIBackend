@php use App\Support\Fmt; @endphp

<div class="stack">

    <x-page-head title="Sales"
                 :sub="Fmt::moneyShort($sales['revenue']).' in 90 days · '.Fmt::num($sales['deals']).' deals · '.Fmt::pct($sales['winRate'], 1).' win rate'">
        <x-slot:actions>
            <div class="seg">
                @foreach (['overview' => 'Overview', 'funnel' => 'Funnel', 'agents' => 'Agents'] as $key => $label)
                    <button class="seg__btn {{ $view === $key ? 'is-active' : '' }}"
                            wire:click="setView('{{ $key }}')">{{ $label }}</button>
                @endforeach
            </div>
            <a class="btn btn--ghost" href="{{ route('app.ask', ['q' => 'Top 5 agents by revenue']) }}" wire:navigate>
                <x-icon name="ask" size="15" /><span>Ask</span>
            </a>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        Revenue, the funnel, and how each agent is doing. Use the tabs above to switch between
        them — **Agents** is where under-performance shows up first.
    </x-page-note>

    <div class="grid g-4">
        <x-kpi label="Revenue · 90 days" :value="$sales['revenue']" format="money"
               :delta="$sales['revenueChange']" ico="sales" help-key="revenue"
               :spark="array_column($sales['series'], 'value')" />

        <x-kpi label="Deals closed" :value="$sales['deals']" format="count"
               :delta="$sales['dealsChange']" ico="check" />

        <x-kpi label="Average deal" :value="$sales['avgDeal']" format="money"
               ico="finance" help-key="avgDeal" />

        <x-kpi label="Win rate" :value="$sales['winRate']" format="pct"
               :delta="$sales['winRateChange']" delta-mode="pts" ico="analytics"
               help-key="winRate" :note="round($sales['cycleDays']).'d avg cycle'" />
    </div>

    <livewire:area-insights area="sales" />

    {{-- ═══════════════════════════════════════════════════════ overview ══ --}}

    @if ($view === 'overview')

        <x-card title="Revenue and volume" sub="Closed deal value by month">
            <x-chart kind="line" size="lg" :payload="$charts['revenue']" />
            <x-chart-note>
                The final month is still running. Compare it to the same point in earlier months,
                not to their totals.
            </x-chart-note>
        </x-card>

        <div class="grid g-2">
            <x-card title="Revenue by location">
                <x-chart kind="hbar" :payload="$charts['byLocation']" />
            </x-card>
            <x-card title="Revenue by property type">
                <x-chart kind="hbar" :payload="$charts['byType']" />
            </x-card>
        </div>

        <x-card title="Closed deals" :sub="Fmt::num($deals->total()).' won deals'" flush>
            <div class="toolbar">
                <input class="input" type="search" placeholder="Search by client…"
                       style="min-width:220px" wire:model.live.debounce.300ms="q">
                <div class="spacer"></div>
            </div>

            <div class="tablewrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th><th>Property</th>
                            <th class="right">Value</th><th class="right">Commission</th>
                            <th>Agent</th><th class="right">Cycle</th><th class="right">Closed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deals as $d)
                            <tr wire:key="deal-{{ $d->id }}">
                                <td>
                                    <div class="row-2">
                                        <x-avatar :name="$d->lead->name ?? '?'" size="sm" />
                                        <span class="cell-primary">{{ $d->lead->name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="t-2">{{ $d->property->title ?? $d->property_type }}</div>
                                    <div class="t-xs t-3">{{ $d->location }}</div>
                                </td>
                                <td class="right"><span class="num semi">{{ Fmt::moneyShort($d->value) }}</span></td>
                                <td class="right"><span class="num t-2">{{ Fmt::moneyShort($d->commission) }}</span></td>
                                <td><span class="t-2">{{ $d->agent->name ?? '—' }}</span></td>
                                <td class="right"><span class="num t-3">{{ $d->cycle_days }}d</span></td>
                                <td class="right"><span class="num t-3">{{ Fmt::date($d->closed_at) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-empty title="No closed deals yet" ico="sales" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($deals->hasPages())
                <x-slot:foot>{{ $deals->links() }}</x-slot:foot>
            @endif
        </x-card>

    {{-- ═════════════════════════════════════════════════════════ funnel ══ --}}

    @elseif ($view === 'funnel')

        <x-card title="Lead funnel" sub="Stage-by-stage conversion across the last 180 days">
            <x-chart kind="funnel" :payload="$charts['funnel']" />
            <x-chart-note>
                Counted on the deepest stage each lead ever reached. A lead lost after a viewing
                still counts as having viewed, which is the only way the drop-off is honest.
            </x-chart-note>
        </x-card>

        @if ($charts['weekly'])
            <x-card title="Viewing → negotiating, week by week"
                    sub="The step where leads are most often lost. A sustained decline here is an execution problem, not a lead-quality one.">
                <x-chart kind="line" :payload="$charts['weekly']" />
                <x-chart-note>
                    Weeks with fewer than three leads are excluded — a 100% conversion computed
                    from two records is noise wearing a number's clothes.
                </x-chart-note>
            </x-card>
        @endif

        <div class="grid g-2">
            <x-card title="Why deals are lost" :sub="Fmt::num($lostCount).' recorded losses'">
                <x-chart kind="hbar" :payload="$charts['reasons']" />
            </x-card>

            <x-card title="Stage detail" flush>
                <div class="tablewrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Stage</th><th class="right">Reached</th>
                                <th class="right">Step conversion</th><th class="right">Lost here</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($funnel['stages'] as $s)
                                <tr>
                                    <td><span class="cell-primary">{{ Fmt::titleCase($s['stage']) }}</span></td>
                                    <td class="right"><span class="num">{{ Fmt::num($s['count']) }}</span></td>
                                    <td class="right">
                                        @if ($s['stage'] === 'new')
                                            <span class="t-4">—</span>
                                        @else
                                            <span class="num {{ $s['conversion'] < 0.5 ? 'neg semi' : '' }}">
                                                {{ Fmt::pct($s['conversion'], 1) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="right">
                                        @if ($s['stage'] === 'new')
                                            <span class="t-4">—</span>
                                        @else
                                            <span class="num t-2">{{ Fmt::num($s['dropped']) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

    {{-- ═════════════════════════════════════════════════════════ agents ══ --}}

    @else

        <x-card title="Close rate against team median"
                :sub="'Median is '.Fmt::pct($baseline, 0).'. Bars in orange sit below two-thirds of it — that is the cohort gap the divergence detector reads.'">
            <x-chart kind="hbar" :payload="$charts['closeRate']" />
        </x-card>

        <x-card title="Team"
                :sub="count($agents['rows']).' agents · top 3 hold '.Fmt::pct($agents['concentration'], 0).' of revenue'"
                flush>
            <div class="tablewrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Agent</th><th class="right">Revenue</th><th class="right">Won</th>
                            <th class="right">Close rate</th><th class="right">Open leads</th>
                            <th class="right">Stale</th><th class="right">Pipeline</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agents['rows'] as $r)
                            @php
                                $low = $r['closeRate'] < $baseline * 0.62;
                                $high = $r['closeRate'] > $baseline * 1.3;
                                // Scaled against twice the team median, so the
                                // median sits at the halfway mark and the eye
                                // reads "above" or "below" without a legend.
                                $meterMax = max(1.0, $baseline * 2);
                                $meterPct = max(2, min(100, $r['closeRate'] / $meterMax * 100));
                            @endphp
                            <tr wire:key="agent-{{ $r['id'] }}">
                                <td>
                                    <div class="row-2">
                                        <x-avatar :name="$r['name']" size="sm" />
                                        <div>
                                            <div class="cell-primary">{{ $r['name'] }}</div>
                                            <div class="t-xs t-3">{{ $r['role'] }} · {{ $r['team'] }}</div>
                                        </div>
                                        @if ($r['coaching'])<x-badge kind="ai">Coaching</x-badge>@endif
                                    </div>
                                </td>
                                <td class="right"><span class="num semi">{{ Fmt::moneyShort($r['revenue']) }}</span></td>
                                <td class="right"><span class="num">{{ Fmt::num($r['won']) }}</span></td>
                                <td class="right">
                                    <div class="row-2" style="justify-content:flex-end">
                                        <span class="num {{ $low ? 'neg semi' : ($high ? 'pos semi' : '') }}">
                                            {{ Fmt::pct($r['closeRate'], 0) }}
                                        </span>
                                        <span class="meter">
                                            <i style="width:{{ $meterPct }}%;
                                                      background:var({{ $low ? '--sev-high' : '--c1' }})"></i>
                                        </span>
                                    </div>
                                </td>
                                <td class="right"><span class="num t-2">{{ Fmt::num($r['openLeads']) }}</span></td>
                                <td class="right">
                                    @if ($r['stale'])
                                        <span class="num neg">{{ Fmt::num($r['stale']) }}</span>
                                    @else
                                        <span class="t-4">—</span>
                                    @endif
                                </td>
                                <td class="right"><span class="num t-2">{{ Fmt::moneyShort($r['pipeline']) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>
