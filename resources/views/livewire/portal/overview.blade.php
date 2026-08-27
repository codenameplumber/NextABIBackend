@php use App\Support\Fmt; @endphp

<div>
    {{-- ─────────────────────────────────── the one thing they came for ── --}}

    <section class="pt-banner">
        <div class="pt-banner__eyebrow">
            <x-icon name="insight" size="13" />
            <span>Hello, {{ $customer->firstName() }}</span>
        </div>
        <h1 class="pt-banner__h">{{ $banner['headline'] }}</h1>
        <p class="pt-banner__p">{!! $banner['body'] !!}</p>

        <div class="row-2 wrap" style="margin-top:var(--s-4);gap:var(--s-2)">
            @unless ($p->settled())
                <a class="btn btn--primary" href="{{ route('portal.payments') }}" wire:navigate>
                    <x-icon name="finance" size="15" /><span>View payment schedule</span>
                </a>
            @endunless
            <a class="btn btn--ghost" href="{{ route('portal.documents') }}" wire:navigate>
                <x-icon name="reports" size="15" /><span>My documents</span>
            </a>
        </div>
    </section>

    {{-- Two lines, not four. The original explained the page's own layout back
         to the reader, which is the sort of copy that fills a gap rather than
         doing a job — and it was the densest block on a screen about
         somebody's money. --}}
    <div class="page-note pt-note">
        <x-icon name="info" size="15" />
        <span>
            Your property, your schedule and your documents — all below. Anything that needs you
            is in the panel above.
        </span>
    </div>

    {{-- ───────────────────────────────────────────────── the property ── --}}

    @if ($p->property)
        <div>
            <div class="pt-sec">
                <div><h2>My property</h2><p>The unit you purchased</p></div>
            </div>

            <div class="prop-card">
                <x-property-art :property="$p->property" />
                <div class="prop-body">
                    <div>
                        <div class="prop-title">{{ $p->property->title }}</div>
                        <div class="prop-sub">{{ $p->property->location }}, Lagos · {{ $p->property->type }}</div>
                    </div>
                    <div class="prop-facts">
                        @foreach (array_filter([
                            'Purchase price' => Fmt::moneyShort($p->deal->value ?? 0),
                            'Size' => Fmt::num($p->property->size_sqm).' sqm',
                            'Bedrooms' => $p->property->bedrooms ?: null,
                            'Completed' => Fmt::date($p->deal?->closed_at),
                        ]) as $k => $v)
                            <div>
                                <div class="prop-fact__k">{{ $k }}</div>
                                <div class="prop-fact__v">{{ $v }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ────────────────────────────────────────────── progress + agent ── --}}

    <div class="pt-grid-2">
        <div class="card">
            <div class="card__head">
                <div>
                    <div class="card__title">Payment progress</div>
                    <div class="card__sub">
                        {{ $p->paid()->count() }} of {{ $p->payments->count() }} instalments received
                    </div>
                </div>
                <a class="btn btn--ghost btn--sm" href="{{ route('portal.payments') }}" wire:navigate>
                    <span>Details</span><x-icon name="right" size="13" />
                </a>
            </div>

            <div class="card__body">
                <div class="pay-progress">
                    <div class="row" style="justify-content:space-between;align-items:baseline">
                        <span style="font-size:var(--t-2xl);font-weight:640;letter-spacing:-.03em">
                            {{ Fmt::pct($p->progress(), 0) }}
                        </span>
                        <span class="t-sm t-3">
                            {{ Fmt::moneyShort($p->paidAmount()) }} of {{ Fmt::moneyShort($p->total()) }}
                        </span>
                    </div>

                    @php
                        $paidPct = $p->progress() * 100;
                        $duePct = $p->total() ? $p->lateAmount() / $p->total() * 100 : 0;
                    @endphp

                    <div class="pay-bar">
                        @if ($paidPct > 0)<i class="paid" style="width:{{ $paidPct }}%"></i>@endif
                        @if ($duePct > 0)<i class="due" style="width:{{ $duePct }}%"></i>@endif
                    </div>

                    <div class="pay-legend">
                        <span><i style="background:var(--pos)"></i>Paid</span>
                        @if ($p->lateCount())
                            <span><i style="background:var(--sev-critical)"></i>Past due</span>
                        @endif
                        <span><i style="background:var(--surface-3)"></i>Remaining</span>
                    </div>
                </div>
            </div>

            @if ($next)
                <div class="card__foot">
                    <div class="row" style="justify-content:space-between;flex-wrap:wrap;gap:10px">
                        <div>
                            <div class="t-sm t-3">
                                Next — instalment {{ $next->instalment }} of {{ $next->of_total }}
                            </div>
                            <div class="semi" style="margin-top:2px">
                                {{ Fmt::money($next->amount) }} · {{ Fmt::date($next->due_at) }}
                            </div>
                        </div>
                        <x-pay-status :status="$next->liveStatus()" />
                    </div>
                </div>
            @endif
        </div>

        <div>
            @if ($p->agent)
                <div class="agent-card">
                    <span class="agent-avatar">{{ Fmt::initials($p->agent->name) }}</span>
                    <div class="grow">
                        <div class="t-xs t-3">Your agent</div>
                        <div class="semi" style="margin-top:2px">{{ $p->agent->name }}</div>
                        <div class="t-sm t-3">{{ $p->agent->role }}</div>
                    </div>
                    <a class="btn btn--ghost btn--sm" href="{{ route('portal.support') }}" wire:navigate>
                        <x-icon name="feedback" size="13" /><span>Message</span>
                    </a>
                </div>
            @endif

            <div class="card" style="margin-top:var(--s-4)">
                <div class="card__head"><div class="card__title">At a glance</div></div>
                <div class="card__body stack-2">
                    @foreach ([
                        'Outstanding balance' => Fmt::moneyShort($p->outstanding()),
                        'Instalments left' => (string) ($p->payments->count() - $p->paid()->count()),
                        'Customer since' => Fmt::date($customer->since),
                        'Reference' => $p->reference(),
                    ] as $k => $v)
                        <div class="row" style="justify-content:space-between;gap:var(--s-4)">
                            <span class="t-sm t-3">{{ $k }}</span>
                            <span class="semi num">{{ $v }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────── purchase journey ── --}}

    <div>
        <div class="pt-sec">
            <div><h2>Your purchase</h2><p>Where you are in the process</p></div>
        </div>

        <div class="card">
            <div class="card__body">
                <div class="tl">
                    @foreach ($journey as $s)
                        <div class="tl-item is-{{ $s['state'] }}">
                            <div class="tl-dot">
                                <x-icon :name="$s['state'] === 'done' ? 'check' : ($s['state'] === 'now' ? 'clock' : 'right')"
                                        size="13" />
                            </div>
                            <div>
                                <div class="tl-t">{{ $s['title'] }}</div>
                                <div class="tl-d">{{ $s['detail'] }}</div>
                                @if ($s['at'])
                                    <div class="tl-when">
                                        {{ Fmt::date($s['at']) }}@if ($s['at']->isFuture()) · expected @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
