@php
    use App\Livewire\Pages\Finance;
    use App\Support\Fmt;
    use App\Support\Vocab;
@endphp

<div class="stack">

    <x-page-head title="Finance"
                 :sub="Fmt::moneyShort($f['collected']).' collected · '.Fmt::moneyShort($f['outstanding']).' outstanding · '.Fmt::pct($f['collectionRatio'], 0).' collection ratio'">
        <x-slot:actions>
            @if ($f['overdueCount'] > 0)
                <button class="btn btn--primary" wire:click="chaseAll"
                        wire:loading.attr="disabled" wire:target="chaseAll">
                    <x-icon name="bolt" size="15" />
                    <span wire:loading.remove wire:target="chaseAll">
                        Chase {{ Fmt::num($f['overdueCount']) }} overdue
                    </span>
                    <span wire:loading wire:target="chaseAll">Sending…</span>
                </button>
            @endif
            <a class="btn btn--ghost" href="{{ route('app.ask', ['q' => 'How much is overdue?']) }}" wire:navigate>
                <x-icon name="ask" size="15" /><span>Ask about cash</span>
            </a>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        Money owed to you. The aging bar shows how long each balance has been outstanding —
        **anything past 90 days rarely clears without a conversation**.
    </x-page-note>

    <div class="grid g-4">
        <x-kpi label="Collected" :value="$f['collected']" format="money" ico="finance"
               :spark="array_column($f['collectedSeries'], 'value')" spark-color="var(--c1)" />

        <x-kpi label="Overdue" :value="$f['overdueAmount']" format="money" ico="warn" invert
               help-key="overdue"
               :note="Fmt::num($f['overdueCount']).' instalments · '.Fmt::num($f['atRiskAccounts']).' accounts'" />

        <x-kpi label="Collection ratio" :value="$f['collectionRatio']" format="pct" ico="check"
               help-key="collectionRatio" note="92% target" />

        <x-kpi label="Due next 30 days" :value="$f['expected30']" format="money" ico="clock" />
    </div>

    <livewire:area-insights area="finance" />

    {{-- ----------------------------------------------------- aging view -- --}}

    <div class="grid g-2">
        <x-card title="Receivables aging" sub="Outstanding balance by how long it has been due">
            <div class="stack-4">
                <div class="aging-bar">
                    @foreach ($f['aging'] as $bucket => $amount)
                        <i style="width:{{ max(0.5, $amount / $agingTotal * 100) }}%;
                                  background:{{ Finance::AGING_COLORS[$bucket] }}"
                           title="{{ $bucket }} days · {{ Fmt::moneyShort($amount) }}"></i>
                    @endforeach
                </div>

                <div class="grid g-4">
                    @foreach ($f['aging'] as $bucket => $amount)
                        <div class="stat">
                            <span class="row-2" style="gap:6px">
                                <i style="width:8px;height:8px;border-radius:2px;
                                          background:{{ Finance::AGING_COLORS[$bucket] }}"></i>
                                <span class="stat__k">{{ $bucket }} days</span>
                            </span>
                            <span class="stat__v" style="font-size:var(--t-lg)">
                                {{ Fmt::moneyShort($amount) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <x-chart-note>
                Balances slide right as they age. A bar that keeps growing on the right is not a
                collections problem any more — it is a relationship problem.
            </x-chart-note>
        </x-card>

        <x-card title="Cash collected" sub="Payments received by month">
            <x-chart kind="line" :payload="$charts['collected']" />
        </x-card>
    </div>

    {{-- ------------------------------------------------- risk accounts -- --}}

    @if (count($f['byCustomer']))
        <x-card title="Accounts at risk"
                :sub="count($f['byCustomer']).' customers carrying overdue balances, largest first'"
                flush>
            @if ($hasVip)
                <x-slot:actions>
                    <button class="btn btn--sm btn--ghost" wire:click="escalateHighValue">
                        <x-icon name="bolt" size="13" /><span>Escalate high-value</span>
                    </button>
                </x-slot:actions>
            @endif

            <div class="tablewrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Customer</th><th class="right">Overdue</th>
                            <th class="right">Instalments</th><th class="right">Days late</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($f['byCustomer'] as $c)
                            <tr wire:key="risk-{{ $c['id'] }}">
                                <td>
                                    <div class="row-2">
                                        <x-avatar :name="$c['name']" size="sm" />
                                        <div>
                                            <div class="cell-primary">{{ $c['name'] }}</div>
                                            <div class="t-xs t-3">
                                                {{ Fmt::titleCase($c['segment'] ?? '') }}@if ($c['rank']) · #{{ $c['rank'] }} by lifetime value @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="right"><span class="num semi">{{ Fmt::moneyShort($c['amount']) }}</span></td>
                                <td class="right"><span class="num">{{ Fmt::num($c['count']) }}</span></td>
                                <td class="right">
                                    <x-badge :kind="$c['maxOverdue'] > 90 ? 'critical' : ($c['maxOverdue'] > 60 ? 'high' : 'medium')">
                                        {{ $c['maxOverdue'] }}d
                                    </x-badge>
                                </td>
                                <td class="right">
                                    <button class="btn btn--sm btn--ghost"
                                            wire:click="remindCustomer({{ $c['id'] }}, @js($c['name']))">
                                        <span>Remind</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- -------------------------------------------------- all payments -- --}}

    <section class="card">
        <div class="card__head">
            <div>
                <div class="card__title">Payment schedule</div>
                <div class="card__sub">{{ Fmt::num($rows->total()) }} instalments</div>
            </div>
        </div>

        <div class="toolbar">
            <input class="input" type="search" placeholder="Search customer…"
                   style="min-width:200px" wire:model.live.debounce.300ms="q">

            <select class="input" wire:model.live="status">
                <option value="">All statuses</option>
                @foreach (['paid', 'scheduled', 'due', 'overdue'] as $s)
                    <option value="{{ $s }}">{{ Fmt::titleCase($s) }}</option>
                @endforeach
            </select>

            <div class="spacer"></div>
        </div>

        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Customer</th><th class="right">Amount</th><th>Status</th>
                        <th class="right">Due</th><th class="right">Paid</th><th>Method</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php $live = $r->liveStatus(); @endphp
                        <tr class="{{ $live === 'overdue' ? 'is-flagged' : '' }}" wire:key="pay-{{ $r->id }}">
                            <td>
                                <div class="cell-primary">{{ $r->customer->name ?? '—' }}</div>
                                <div class="t-xs t-3">Instalment {{ $r->instalment }} of {{ $r->of_total }}</div>
                            </td>
                            <td class="right"><span class="num">{{ Fmt::moneyShort($r->amount) }}</span></td>
                            <td><x-badge :kind="Vocab::statusKind($live)" dot>{{ Fmt::titleCase($live) }}</x-badge></td>
                            <td class="right">
                                <span class="num {{ $live === 'overdue' ? 'neg semi' : 't-2' }}">
                                    {{ Fmt::date($r->due_at) }}
                                </span>
                            </td>
                            <td class="right">
                                <span class="num t-3">{{ $r->paid_at ? Fmt::date($r->paid_at) : '—' }}</span>
                            </td>
                            <td><span class="t-3 t-sm">{{ $r->method }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty title="Nothing here" ico="filter"
                                         msg="No payments match those filters." />
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
</div>
