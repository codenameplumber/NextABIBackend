@php use App\Support\Fmt; @endphp

<div class="stack">
    <x-page-head title="Commitments"
                 :sub="$counts['pending'].' awaiting approval · '.$counts['active'].' paying'" />

    <x-page-note>
        Approving generates the instalment plan and **freezes the commission rate onto the sale**.
        A commitment cannot be approved while its investor has no realtor — commission has to
        have an owner.
    </x-page-note>

    <livewire:area-insights area="sales" />

    <div class="row-2 wrap" style="gap:var(--s-3);align-items:center">
        <div class="queue-tabs" style="margin:0">
            @foreach (['pending' => 'Awaiting approval', 'active' => 'Paying', 'completed' => 'Settled', 'all' => 'Everything'] as $key => $label)
                <button class="queue-tab {{ $filter === $key ? 'is-on' : '' }}"
                        wire:click="$set('filter', '{{ $key }}')">
                    {{ $label }}
                    <span class="t-xs" style="opacity:.7">{{ $counts[$key] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <span class="grow"></span>

        <input class="input" type="search" style="min-width:230px"
               wire:model.live.debounce.300ms="search"
               placeholder="Investor, property or reference…">
    </div>

    @if ($rows->isEmpty())
        <x-empty title="Nothing here"
                 :msg="$search
                    ? 'Nothing matches that search.'
                    : ($filter === 'pending' ? 'No commitments waiting on you.' : 'Nothing matches that filter.')" />
    @else
        {{-- A table, not cards.

             This is a queue that runs to sixty rows, and the operator's job is
             to scan it for the ones that need something — which is a comparison
             task. Cards stack every row's detail vertically and make comparing
             two of them a scroll; a row per commitment puts status, value and
             blocker in the same column every time. The detail lives in the
             canvas, one click away. --}}
        <x-card flush>
            <div class="tablewrap">
                <table class="table table--rows">
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th>Investor</th>
                            <th>Realtor</th>
                            <th class="right">Value</th>
                            <th>Term</th>
                            <th>Status</th>
                            <th class="right">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $c)
                            @php
                                $isBlocked = $c->isPending() && $c->approvalBlocker() !== null;
                                $paid = $c->paidCount();
                                $of = $c->instalments->count();
                            @endphp

                            <tr wire:key="c-{{ $c->id }}"
                                class="rowlink {{ $openId === $c->id ? 'is-open' : '' }} {{ $isBlocked ? 'is-flagged' : '' }}"
                                wire:click="open({{ $c->id }})"
                                tabindex="0"
                                wire:keydown.enter="open({{ $c->id }})">

                                <td>
                                    <div class="cell-primary">{{ $c->property?->title ?? 'Property removed' }}</div>
                                    <div class="t-xs t-3">
                                        {{ $c->property?->location }} · {{ $c->property?->ref }}
                                    </div>
                                </td>

                                <td>
                                    <div>{{ $c->customer?->name ?? '—' }}</div>
                                    <div class="t-xs t-3">{{ Fmt::ago($c->committed_at) }}</div>
                                </td>

                                <td>
                                    @if ($c->customer?->realtor)
                                        <div>{{ $c->customer->realtor->name }}</div>
                                        <div class="t-xs t-3 mono">{{ $c->customer->realtor->code }}</div>
                                    @else
                                        <span class="badge badge--neg">
                                            <x-icon name="warn" size="11" /><span>None</span>
                                        </span>
                                    @endif
                                </td>

                                <td class="right"><span class="num semi">{{ Fmt::moneyShort($c->value) }}</span></td>

                                <td class="t-2">
                                    {{ $c->duration_months ? $c->duration_months.' mo' : '—' }}
                                </td>

                                <td>
                                    <x-badge :kind="match($c->status) {
                                        'completed' => 'pos',
                                        'active', 'approved' => 'info',
                                        'pending' => 'medium',
                                        default => 'low',
                                    }">{{ $c->statusLabel() }}</x-badge>
                                </td>

                                <td class="right">
                                    @if ($of)
                                        <span class="num t-2">{{ $paid }}/{{ $of }}</span>
                                        <div class="jbar jbar--thin" style="margin-top:4px">
                                            <div class="jbar__f" style="width:{{ round($c->progress() * 100, 1) }}%"></div>
                                        </div>
                                    @else
                                        <span class="t-4">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- ───────────────────────────────────────────────── the side canvas ──
         Everything about one commitment, without leaving the queue. The
         approval decision needs the investor, the realtor, the term and the
         blocker in one place — and putting it on its own page would mean a
         round trip back to the list for every row worked. --}}

    @if ($detail)
        <div class="scrim" wire:click.self="closeDrawer" wire:keydown.escape.window="closeDrawer">
            <div class="drawer" role="dialog" aria-modal="true" aria-label="Commitment detail">
                <header class="overlay__head">
                    <div style="min-width:0">
                        <h3 style="margin:0">{{ $detail->property?->title ?? 'Property removed' }}</h3>
                        <div class="t-sm t-3">
                            {{ $detail->property?->location }} · {{ $detail->property?->ref }}
                        </div>
                    </div>
                    <button class="btn btn--sm btn--icon" wire:click="closeDrawer" aria-label="Close">
                        <x-icon name="x" size="15" />
                    </button>
                </header>

                <div class="overlay__body">
                    <div class="stack-4">

                        <div class="row-2 wrap">
                            <x-badge :kind="match($detail->status) {
                                'completed' => 'pos',
                                'active', 'approved' => 'info',
                                'pending' => 'medium',
                                default => 'low',
                            }" dot>{{ $detail->statusLabel() }}</x-badge>
                            <span class="t-sm t-3">Committed {{ Fmt::ago($detail->committed_at) }}</span>
                        </div>

                        {{-- The blocker, and its fix, in the same place. Two
                             clicks here beats a correct error message and a
                             trip to another screen to act on it. --}}
                        @if ($blocker)
                            <div class="blocker">
                                <x-icon name="warn" size="15" />
                                <span>{{ $blocker }}</span>
                            </div>
                        @endif

                        <div class="dl">
                            <div><span class="dl__k">Investor</span>
                                <span class="dl__v">{{ $detail->customer?->name ?? '—' }}</span></div>
                            <div><span class="dl__k">Where</span>
                                <span class="dl__v">{{ $detail->customer?->placeLabel() ?? '—' }}</span></div>
                            <div><span class="dl__k">Heard about us</span>
                                <span class="dl__v">{{ $detail->customer?->sourceLabel() ?? '—' }}</span></div>
                            <div><span class="dl__k">Value</span>
                                <span class="dl__v num">{{ Fmt::money($detail->value) }}</span></div>
                            <div><span class="dl__k">Term</span>
                                <span class="dl__v">{{ $detail->duration_months ? $detail->duration_months.' months' : 'no term' }}</span></div>
                            <div><span class="dl__k">Monthly</span>
                                <span class="dl__v num">
                                    {{ $detail->duration_months ? Fmt::money($detail->value / $detail->duration_months) : '—' }}
                                </span></div>
                            <div><span class="dl__k">Realtor</span>
                                <span class="dl__v">{{ $detail->customer?->realtor?->name ?? '— none —' }}</span></div>
                            <div><span class="dl__k">Commission</span>
                                <span class="dl__v num">
                                    @if ($detail->commission_rate)
                                        {{ Fmt::money($detail->commissionDue()) }}
                                        <span class="t-xs t-3">({{ number_format($detail->commission_rate * 100, 1) }}% frozen)</span>
                                    @else
                                        <span class="t-4">set at approval</span>
                                    @endif
                                </span></div>
                            @if ($detail->approver)
                                <div><span class="dl__k">Approved by</span>
                                    <span class="dl__v">{{ $detail->approver->name }}, {{ Fmt::date($detail->approved_at) }}</span></div>
                            @endif
                        </div>

                        {{-- ---- assign a realtor, searchable ---- --}}
                        @if ($detail->isPending() && ! $detail->customer?->isAssigned())
                            <div class="picker">
                                <div class="eyebrow">Attach a realtor</div>

                                <input class="input" type="search"
                                       wire:model.live.debounce.250ms="realtorSearch"
                                       placeholder="Search by name, code or email…">

                                @if ($realtorOptions->isEmpty())
                                    <p class="t-sm t-3" style="margin:var(--s-3) 0 0">
                                        No approved realtor matches that.
                                    </p>
                                @else
                                    <div class="picker__list">
                                        @foreach ($realtorOptions as $r)
                                            <label class="picker__opt" wire:key="opt-{{ $r->id }}">
                                                <input type="radio" name="realtor"
                                                       value="{{ $r->id }}"
                                                       wire:model.live="assignTo.{{ $detail->id }}">
                                                <span class="grow">{{ $r->name }}</span>
                                                <span class="mono t-sm t-3">{{ $r->code }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Inline margin rather than .mt-8: that utility
                                     lives in editorial.css, which the console
                                     layout does not load. --}}
                                <button class="btn btn--sm" style="margin-top:var(--s-3)"
                                        wire:click="assign({{ $detail->id }})"
                                        @disabled(empty($assignTo[$detail->id]))>
                                    <x-icon name="check" size="13" /><span>Attach</span>
                                </button>
                            </div>
                        @endif

                        {{-- ---- the instalment journey ---- --}}
                        @if ($detail->instalments->isNotEmpty())
                            @php $progress = $detail->progress(); @endphp
                            <div>
                                <div class="row-2" style="justify-content:space-between;margin-bottom:6px">
                                    <span class="t-sm t-3">
                                        {{ $detail->paidCount() }} of {{ $detail->instalments->count() }} instalments received
                                    </span>
                                    <span class="t-sm semi">{{ Fmt::pct($progress, 0) }}</span>
                                </div>
                                <div class="jbar">
                                    <div class="jbar__f" style="width:{{ round($progress * 100, 1) }}%"></div>
                                </div>
                                <div class="t-sm t-3" style="margin-top:6px">
                                    {{ Fmt::money($detail->paidAmount()) }} received ·
                                    {{ Fmt::money($detail->outstanding()) }} outstanding
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <footer class="overlay__foot">
                    @if ($detail->isPending())
                        <button class="btn btn--ghost btn--sm"
                                wire:click="decline({{ $detail->id }})"
                                wire:confirm="Decline this commitment? The unit goes back on the market.">
                            <span>Decline</span>
                        </button>
                        <span class="grow"></span>
                        <button class="btn btn--primary"
                                wire:click="approve({{ $detail->id }})"
                                wire:loading.attr="disabled"
                                @disabled((bool) $blocker)>
                            <x-icon name="check" size="14" /><span>Approve</span>
                        </button>
                    @elseif ($detail->isLive())
                        <span class="grow"></span>
                        <button class="btn btn--ghost btn--sm"
                                wire:click="cancel({{ $detail->id }})"
                                wire:confirm="Cancel this commitment? Unpaid instalments are removed, the unit goes back on the market, and any commission already paid becomes a debt.">
                            <span>Cancel commitment</span>
                        </button>
                    @else
                        <span class="t-sm t-3">This commitment is settled. Nothing left to decide.</span>
                    @endif
                </footer>
            </div>
        </div>
    @endif
</div>
