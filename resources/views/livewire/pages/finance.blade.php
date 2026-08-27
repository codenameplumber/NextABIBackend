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

    {{-- The three figures this screen is asked for first. --}}
    <div class="grid g-3">
        <x-kpi label="Assets sold" :value="$assetsSoldValue" format="money" ico="property"
               :note="Fmt::num($assetsSold).' commitments approved'" />
        <x-kpi label="Commission paid" :value="$commissionPaid" format="money" ico="check"
               note="Settled with realtors" />
        <x-kpi label="Commission owed" :value="$commissionOwed" format="money" ico="agent" invert
               :note="$commissionOwed > 0 ? 'Awaiting payout' : 'All settled'" />
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
                        <th class="right">Due</th><th class="right">Paid</th><th>Record</th>
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
                            {{-- Money arrives as a bank transfer and somebody keys
                                 it in. The reference is required, because a disputed
                                 payment without one is a conversation nobody wins. --}}
                            <td>
                                @if ($live === 'paid')
                                    <div class="row-2" style="gap:var(--s-2)">
                                        <span class="t-3 t-xs mono">{{ $r->reference ?? $r->method }}</span>
                                        <button class="btn btn--ghost btn--sm"
                                                wire:click="reversePayment({{ $r->id }})"
                                                wire:confirm="Reverse this recording?">
                                            <span>Undo</span>
                                        </button>
                                    </div>
                                @else
                                    <div class="row-2" style="gap:var(--s-2)">
                                        <input class="input" style="min-width:120px;max-width:150px"
                                               placeholder="Transfer ref"
                                               wire:model="reference.{{ $r->id }}">
                                        <button class="btn btn--sm"
                                                wire:click="recordPayment({{ $r->id }})">
                                            <span>Record</span>
                                        </button>
                                    </div>
                                @endif
                            </td>
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

    {{-- ─────────────────────────────────────────── commission payouts ──
         Owed first: an unpaid obligation is the thing that needs a decision,
         and a settled one is a record.                                    --}}

    @if ($payouts->isNotEmpty())
        <x-card title="Commission" :sub="Fmt::money($commissionOwed).' owed across '.$payouts->where('status', 'owed')->count().' sales'">
            <div class="tablewrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Realtor</th><th>Property</th><th class="right">Sale value</th>
                            <th class="right">Rate</th><th class="right">Commission</th>
                            <th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payouts as $po)
                            <tr wire:key="po-{{ $po->id }}">
                                <td>
                                    <div class="cell-primary">{{ $po->realtor?->name ?? '—' }}</div>
                                    <div class="t-xs t-3 mono">{{ $po->realtor?->code }}</div>
                                </td>
                                <td>
                                    <div class="t-sm">{{ $po->commitment?->property?->title ?? '—' }}</div>
                                    <div class="t-xs t-3">{{ $po->commitment?->property?->ref }}</div>
                                </td>
                                <td class="right">{{ Fmt::money($po->commitment?->value ?? 0) }}</td>
                                <td class="right">{{ number_format($po->rate * 100, 1) }}%</td>
                                <td class="right semi">{{ Fmt::money($po->amount) }}</td>
                                <td>
                                    <x-badge :kind="$po->statusKind()">{{ $po->statusLabel() }}</x-badge>
                                </td>
                                <td class="right">
                                    @if ($po->isWrittenOff())
                                        <span class="t-xs t-3">Sale cancelled</span>
                                    @elseif ($po->isPaid())
                                        <span class="t-xs t-3">{{ Fmt::dateShort($po->paid_at) }}</span>
                                    @else
                                        <button class="btn btn--sm" wire:click="payCommission({{ $po->id }})"
                                                wire:confirm="Mark {{ Fmt::money($po->amount) }} as paid to {{ $po->realtor?->name }}?">
                                            <span>Mark paid</span>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>
