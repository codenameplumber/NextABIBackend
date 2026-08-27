@php use App\Support\Fmt; @endphp

<div class="stack">
    <x-page-head title="Realtors"
                 :sub="$counts['approved'].' approved · '.$counts['pending'].' awaiting approval'" />

    <x-page-note>
        Approval is what issues a realtor their code. **A realtor sitting unapproved is leads
        nobody is capturing** — this queue is worth clearing first thing.
    </x-page-note>

    <div class="queue-tabs">
        @foreach (['pending' => 'Awaiting approval', 'approved' => 'Approved', 'suspended' => 'Suspended', 'all' => 'Everyone'] as $key => $label)
            <button class="queue-tab {{ $filter === $key ? 'is-on' : '' }}"
                    wire:click="$set('filter', '{{ $key }}')">
                {{ $label }}
                <span class="t-xs" style="opacity:.7">{{ $counts[$key] ?? 0 }}</span>
            </button>
        @endforeach

        <span class="spacer"></span>

        <input type="search" class="input" style="min-width:220px"
               placeholder="Name, email or code"
               wire:model.live.debounce.400ms="search">
    </div>

    @if ($rows->isEmpty())
        <x-empty title="Nothing here"
                 :msg="$filter === 'pending' ? 'No applications waiting. Good.' : 'No realtors match that.'" />
    @else
        <x-card>
            <div class="tablewrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="is-sortable {{ $sort === 'name' ? 'is-sorted' : '' }}"
                                wire:click="sortBy('name')">Realtor</th>
                            <th class="is-sortable {{ $sort === 'code' ? 'is-sorted' : '' }}"
                                wire:click="sortBy('code')">Code</th>
                            <th>Contact</th>
                            <th class="num is-sortable {{ $sort === 'investors' ? 'is-sorted' : '' }}"
                                wire:click="sortBy('investors')">Investors</th>
                            <th class="num is-sortable {{ $sort === 'leads' ? 'is-sorted' : '' }}"
                                wire:click="sortBy('leads')">Leads</th>
                            <th class="num">Earned</th>
                            <th class="num">Owed</th>
                            <th class="is-sortable {{ $sort === 'status' ? 'is-sorted' : '' }}"
                                wire:click="sortBy('status')">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php
                                $p = $payouts[$r->id] ?? null;
                                $earned = (float) ($p->total ?? 0);
                                $paid = (float) ($p->paid ?? 0);
                            @endphp
                            <tr>
                                <td>
                                    <div class="row-2">
                                        <x-avatar :name="$r->name" />
                                        <div>
                                            <div class="semi">{{ $r->name }}</div>
                                            <div class="t-xs t-3">
                                                Joined {{ Fmt::dateShort($r->created_at) }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="mono">{{ $r->code ?? '—' }}</td>
                                <td>
                                    <div class="t-sm">{{ $r->email }}</div>
                                    <div class="t-xs t-3">{{ $r->phone ?? '—' }}</div>
                                </td>
                                <td class="num">{{ Fmt::num($r->investors_count) }}</td>
                                <td class="num">{{ Fmt::num($r->leads_count) }}</td>
                                <td class="num">{{ $earned > 0 ? Fmt::moneyShort($earned) : '—' }}</td>
                                <td class="num">
                                    {{ $earned - $paid > 0 ? Fmt::moneyShort($earned - $paid) : '—' }}
                                </td>
                                <td>
                                    <x-badge :kind="match($r->status) {
                                        'approved' => 'pos',
                                        'pending' => 'medium',
                                        default => 'neg',
                                    }">{{ $r->statusLabel() }}</x-badge>
                                </td>
                                <td>
                                    <div class="row-2" style="gap:var(--s-2);justify-content:flex-end">
                                        @if ($r->status === 'pending')
                                            <button class="btn btn--primary btn--sm"
                                                    wire:click="approve({{ $r->id }})"
                                                    wire:loading.attr="disabled">
                                                <x-icon name="check" size="13" /><span>Approve</span>
                                            </button>
                                        @elseif ($r->status === 'approved')
                                            <button class="btn btn--ghost btn--sm"
                                                    data-copy="{{ $r->registrationLink() }}">
                                                <span>Copy link</span>
                                            </button>
                                            <button class="btn btn--ghost btn--sm"
                                                    wire:click="suspend({{ $r->id }})"
                                                    wire:confirm="Suspend {{ $r->firstName() }}? Their existing investors keep their owner.">
                                                <span>Suspend</span>
                                            </button>
                                        @else
                                            <button class="btn btn--sm"
                                                    wire:click="approve({{ $r->id }})">
                                                <span>Reinstate</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>
