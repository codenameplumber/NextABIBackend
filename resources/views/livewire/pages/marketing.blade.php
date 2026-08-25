@php
    use App\Support\Fmt;
    use App\Support\Vocab;
@endphp

<div class="stack">

    <x-page-head title="Marketing"
                 :sub="Fmt::moneyShort($mk['spend']).' spent · '.Fmt::num($mk['leads']).' leads · '.number_format($mk['roas'], 2).'× return on spend'">
        <x-slot:actions>
            <a class="btn btn--ghost"
               href="{{ route('app.ask', ['q' => 'Show me the worst campaigns by ROAS']) }}" wire:navigate>
                <x-icon name="ask" size="15" /><span>Ask about campaigns</span>
            </a>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        What you spend and what comes back. **ROAS below 1× means the campaign costs more than
        it returns** — those rows are worth a decision today.
    </x-page-note>

    <div class="grid g-4">
        <x-kpi label="Total spend" :value="$mk['spend']" format="money" ico="marketing"
               :note="count($mk['active']).' active campaigns'" />

        <x-kpi label="Cost per lead" :value="$mk['cpl']" format="moneyFull" ico="leads"
               :note="'median '.Fmt::moneyShort($mk['baselineCpl'])" invert />

        <x-kpi label="Acquisition cost" :value="$mk['cac']" format="moneyFull" ico="customers"
               help-key="cac" :note="Fmt::num($mk['won']).' customers'" invert />

        <x-kpi label="Return on spend" :value="$mk['roas']" format="x" ico="sales"
               help-key="roas" :note="Fmt::moneyShort($mk['revenue']).' attributed'" />
    </div>

    <livewire:area-insights area="marketing" />

    <div class="grid g-2">
        <x-card title="Spend by channel" sub="Where the budget goes">
            <x-chart kind="donut" :payload="$charts['spend']" />
        </x-card>

        <x-card title="Return by channel" sub="Revenue generated per naira spent">
            <x-chart kind="hbar" :payload="$charts['roas']" />
            <x-chart-note>
                Orange bars sit below 1×. A channel can look busy and still be the one taking
                money off the table.
            </x-chart-note>
        </x-card>
    </div>

    <x-card title="Campaigns" :sub="count($mk['campaigns']).' campaigns · ordered by spend'" flush>
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Campaign</th><th>Status</th>
                        <th class="right">Spend</th><th class="right">Leads</th>
                        <th class="right">Closed</th><th class="right">CPL</th>
                        <th class="right">ROAS</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mk['campaigns'] as $c)
                        @php
                            // Flagged where a campaign has enough leads to judge
                            // and still converts well below the portfolio median.
                            $weak = $c['leads'] >= 12 && $c['conversion'] < $mk['baselineConversion'] * 0.62;
                        @endphp
                        <tr class="{{ $weak ? 'is-flagged' : '' }}" wire:key="camp-{{ $c['id'] }}">
                            <td>
                                <div class="cell-primary">{{ $c['name'] }}</div>
                                <div class="t-xs t-3">
                                    {{ $c['channel'] }} · {{ $c['source'] }} · {{ Fmt::date($c['startAt']) }}
                                </div>
                            </td>
                            <td><x-badge :kind="Vocab::statusKind($c['status'])" dot>{{ Fmt::titleCase($c['status']) }}</x-badge></td>
                            <td class="right"><span class="num semi">{{ Fmt::moneyShort($c['spend']) }}</span></td>
                            <td class="right"><span class="num">{{ Fmt::num($c['leads']) }}</span></td>
                            <td class="right"><span class="num">{{ Fmt::num($c['won']) }}</span></td>
                            <td class="right">
                                <span class="num {{ $c['cpl'] > $mk['baselineCpl'] * 1.8 ? 'neg' : 't-2' }}">
                                    {{ $c['leads'] ? Fmt::money(round($c['cpl'])) : '—' }}
                                </span>
                            </td>
                            <td class="right">
                                <x-badge :kind="$c['roas'] >= 3 ? 'pos' : ($c['roas'] >= 1 ? '' : 'neg')">
                                    {{ number_format($c['roas'], 2) }}×
                                </x-badge>
                            </td>
                            <td class="right">
                                @if ($c['status'] === 'active')
                                    <button class="btn btn--sm btn--ghost"
                                            wire:click="pause({{ $c['id'] }}, @js($c['name']))">
                                        <span>Pause</span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="No campaigns" ico="marketing" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
