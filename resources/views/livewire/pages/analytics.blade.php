@php use App\Support\Fmt; @endphp

<div class="stack">

    <x-page-head title="Analytics"
                 sub="Cross-module trends. Every series here is computed live from the same tables the modules read.">
        <x-slot:actions>
            <div class="seg">
                @foreach (['growth' => 'Growth', 'efficiency' => 'Efficiency', 'mix' => 'Mix'] as $key => $label)
                    <button class="seg__btn {{ $lens === $key ? 'is-active' : '' }}"
                            wire:click="setLens('{{ $key }}')">{{ $label }}</button>
                @endforeach
            </div>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        Trends that cut across modules. Use this to check whether something you saw elsewhere is
        **a blip or a direction**.
    </x-page-note>

    {{-- ═════════════════════════════════════════════════════════ growth ══ --}}

    @if ($lens === 'growth')

        <div class="grid g-4">
            <x-kpi label="Revenue trend" :value="$revenueTrend" format="pct" ico="sales" note="per month" />
            <x-kpi label="Lead volume trend" :value="$leadTrend" format="pct" ico="leads" note="per month" />
            <x-kpi label="Customers" :value="$customers['total']" format="count" ico="customers"
                   :spark="array_column($customers['newSeries'], 'value')" />
            <x-kpi label="Avg deal size" :value="$sales['avgDeal']" format="money" ico="finance"
                   help-key="avgDeal" />
        </div>

        <x-card title="Revenue" sub="Monthly closed value">
            <x-chart kind="line" size="lg" :payload="$charts['revenue']" />
            <x-chart-note>
                The trend figure above is fitted to this line after smoothing, so one strong month
                does not turn a flat business into a growing one.
            </x-chart-note>
        </x-card>

        {{-- Two measures, two charts — never a second y-axis. --}}
        <div class="grid g-2">
            <x-card title="Lead volume" sub="New leads per month">
                <x-chart kind="line" :payload="$charts['leadVolume']" />
            </x-card>
            <x-card title="Deals closed" sub="Won deals per month">
                <x-chart kind="bar" :payload="$charts['deals']" />
            </x-card>
        </div>

    {{-- ═════════════════════════════════════════════════════ efficiency ══ --}}

    @elseif ($lens === 'efficiency')

        <div class="grid g-4">
            <x-kpi label="Cost per lead" :value="$marketing['cpl']" format="moneyFull"
                   ico="marketing" invert />
            <x-kpi label="Acquisition cost" :value="$marketing['cac']" format="moneyFull"
                   ico="customers" help-key="cac" invert />
            <x-kpi label="LTV : CAC" :value="$ltvCac" format="x" ico="sales" note="3× is healthy" />
            <x-kpi label="Sales cycle" :value="$sales['cycleDays']" format="days" ico="clock"
                   help-key="cycle" invert />
        </div>

        <div class="grid g-2">
            <x-card title="Conversion by source">
                <x-chart kind="hbar" :payload="$charts['conversion']" />
            </x-card>
            <x-card title="Cost per lead by channel">
                <x-chart kind="hbar" :payload="$charts['cpl']" />
            </x-card>
        </div>

        <x-card title="Time on market by property type" sub="Average days from listing to sale">
            <x-chart kind="bar" :payload="$charts['domByType']" />
            <x-chart-note>
                Types with fewer than three sales are excluded — an average drawn from one sale is
                that sale, not an average.
            </x-chart-note>
        </x-card>

    {{-- ════════════════════════════════════════════════════════════ mix ══ --}}

    @else

        <div class="grid g-2">
            <x-card title="Revenue by location">
                <x-chart kind="donut" :payload="$charts['revenueMix']" />
            </x-card>
            <x-card title="Lead mix by source">
                <x-chart kind="donut" :payload="$charts['leadMix']" />
            </x-card>
        </div>

        <x-card title="Inventory status by location" sub="Available against sold">
            <x-chart kind="stacked" size="lg" :payload="$charts['inventory']" />
        </x-card>

        <x-card title="Customer segments" flush>
            <div class="tablewrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Segment</th><th class="right">Customers</th>
                            <th class="right">Total value</th><th class="right">Average</th>
                            <th class="right">Satisfaction</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (collect($customers['bySegment'])->sortByDesc('ltv') as $s)
                            <tr wire:key="seg-{{ $s['key'] }}">
                                <td><x-badge>{{ Fmt::titleCase($s['key']) }}</x-badge></td>
                                <td class="right"><span class="num">{{ Fmt::num($s['count']) }}</span></td>
                                <td class="right"><span class="num semi">{{ Fmt::moneyShort($s['ltv']) }}</span></td>
                                <td class="right"><span class="num t-2">{{ Fmt::moneyShort($s['avgLtv']) }}</span></td>
                                <td class="right">
                                    <span class="num t-2">
                                        {{ $s['satisfaction'] ? number_format($s['satisfaction'], 2) : '—' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>
