@php use App\Support\Fmt; @endphp

<div>
    <h1 class="display--sm">Documents</h1>

    <div class="page-note pt-note">
        <x-icon name="info" size="15" />
        <span>
            Every document issued on your purchase, and what each outstanding one is waiting on.
            Nothing here is pending because we forgot about it.
        </span>
    </div>

    {{-- ──────────────────────────────────────────────── available now ── --}}

    <div class="card">
        <div class="card__head">
            <div>
                <div class="card__title">Available now</div>
                <div class="card__sub">
                    {{ $ready->count() }} documents · {{ $p->paid()->count() }} payment receipts
                </div>
            </div>

            @if ($ready->count() > 1)
                <button class="btn btn--ghost btn--sm" wire:click="downloadAll"
                        wire:loading.attr="disabled" wire:target="downloadAll">
                    <x-icon name="download" size="13" />
                    <span wire:loading.remove wire:target="downloadAll">Download all</span>
                    <span wire:loading wire:target="downloadAll">Zipping…</span>
                </button>
            @endif
        </div>

        <div>
            @foreach ($ready as $d)
                <div class="doc-row is-ready" wire:key="doc-{{ $d['key'] }}">
                    <div class="doc-icon"><x-icon name="reports" size="16" /></div>
                    <div class="grow">
                        <div class="doc-name">{{ $d['name'] }}</div>
                        <div class="doc-meta">
                            {{ $d['note'] }}@if ($d['at']) · issued {{ Fmt::date($d['at']) }}@endif
                        </div>
                    </div>
                    <x-badge kind="pos" dot>Ready</x-badge>
                    <button class="btn btn--ghost btn--sm" wire:click="download('{{ $d['key'] }}')">
                        <x-icon name="download" size="13" /><span>Download</span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ───────────────────────────────────────────────── still to come ── --}}

    @if ($pending->isNotEmpty())
        <div class="card">
            <div class="card__head">
                <div>
                    <div class="card__title">Still to come</div>
                    <div class="card__sub">What each one is waiting on</div>
                </div>
            </div>

            <div>
                @foreach ($pending as $d)
                    <div class="doc-row" wire:key="pend-{{ $d['key'] }}">
                        <div class="doc-icon"><x-icon name="clock" size="16" /></div>
                        <div class="grow">
                            <div class="doc-name" style="color:var(--text-2)">{{ $d['name'] }}</div>
                            <div class="doc-meta">{{ $d['note'] }}</div>
                        </div>
                        <x-badge :kind="! empty($d['blockedBy']) ? 'medium' : ''">
                            {{ $d['blockedBy'] ?? 'Pending' }}
                        </x-badge>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ────────────────────────────────────────────────────── receipts ── --}}

    @if ($p->paid()->isNotEmpty())
        <div>
            <div class="pt-sec">
                <div>
                    <h2>Payment receipts</h2>
                    <p>One for each of the {{ $p->paid()->count() }} instalments received</p>
                </div>
            </div>

            <div class="card">
                @foreach ($p->paid() as $pay)
                    <div class="doc-row is-ready" wire:key="rc-{{ $pay->id }}">
                        <div class="doc-icon"><x-icon name="finance" size="16" /></div>
                        <div class="grow">
                            <div class="doc-name">Instalment {{ $pay->instalment }} of {{ $pay->of_total }}</div>
                            <div class="doc-meta">
                                {{ Fmt::money($pay->amount) }} · paid {{ Fmt::date($pay->paid_at) }} · {{ $pay->method }}
                            </div>
                        </div>
                        <button class="btn btn--ghost btn--sm" wire:click="receipt({{ $pay->id }})">
                            <x-icon name="download" size="13" /><span>Receipt</span>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
