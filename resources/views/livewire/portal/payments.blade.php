@php
    use App\Support\Fmt;

    $remaining = $p->payments->count() - $p->paid()->count();
@endphp

<div>
    <div>
        <h1 class="display--sm">Payments</h1>
        <p class="lede" style="font-size:var(--t-md);margin-top:8px">
            @if ($p->settled())
                This purchase is fully settled.
            @else
                {{ Fmt::moneyShort($p->outstanding()) }} remaining across {{ $remaining }} instalments.
            @endif
        </p>
    </div>

    <div class="page-note pt-note">
        <x-icon name="info" size="15" />
        <span>
            Your full instalment schedule. Every instalment you have paid has a receipt you can
            download, and the questions at the bottom cover what people ask us most.
        </span>
    </div>

    {{-- ──────────────────────────────────────────────────── summary ── --}}

    <section class="pay-sum">
        <div class="pay-sum__hero">
            <div class="pay-sum__top">
                <div>
                    <div class="pay-sum__k">
                        {{ $p->settled() ? 'Fully settled' : 'Outstanding balance' }}
                    </div>
                    <div class="pay-sum__v {{ $p->settled() ? 'is-clear' : '' }}">
                        {{ Fmt::money($p->settled() ? $p->total() : $p->outstanding()) }}
                    </div>
                </div>
                <div class="pay-sum__pct">
                    <b>{{ Fmt::pct($p->progress(), 0) }}</b>
                    <span>of the price paid</span>
                </div>
            </div>

            {{-- One segment per instalment, weighted by amount. The whole plan
                 is legible in a single glance without reading the table. --}}
            <div class="pay-bar">
                @foreach ($p->payments as $pay)
                    @php
                        $st = $pay->liveStatus();
                        $colour = match (true) {
                            $st === 'paid' => 'var(--pos)',
                            $st === 'overdue' => 'var(--sev-critical)',
                            $st === 'due' => 'var(--sev-high)',
                            $next && $pay->id === $next->id => 'var(--brand-500)',
                            default => 'var(--surface-3)',
                        };
                    @endphp
                    <i style="width:{{ $p->total() ? $pay->amount / $p->total() * 100 : 0 }}%;background:{{ $colour }}"
                       title="Instalment {{ $pay->instalment }} of {{ $pay->of_total }} · {{ Fmt::money($pay->amount) }} · due {{ Fmt::date($pay->due_at) }}"></i>
                @endforeach
            </div>

            @php
                // Assembled in PHP rather than nested inline directives: the
                // sentence has four legitimate endings and Blade is not the
                // right place to branch four ways mid-clause.
                if ($p->settled()) {
                    $meta = 'All <b>'.$p->payments->count().'</b> instalments received. '
                        .'Nothing further is due.';
                } else {
                    $meta = '<b>'.$p->paid()->count().'</b> of <b>'.$p->payments->count()
                        .'</b> instalments received';
                    $meta .= $next
                        ? ' — next is <b>'.Fmt::money($next->amount).'</b> on <b>'
                            .Fmt::date($next->due_at).'</b>'
                            .($p->overdue()->count() ? ', which is already past due.' : '.')
                        : '.';
                }
            @endphp

            <div class="pay-sum__meta">{!! $meta !!}</div>

            <div class="pay-legend">
                <span><i style="background:var(--pos)"></i>Paid</span>
                @if ($p->overdue()->count())<span><i style="background:var(--sev-critical)"></i>Overdue</span>@endif
                @if ($p->due()->count())<span><i style="background:var(--sev-high)"></i>Due now</span>@endif
                @if ($p->upcoming()->count())<span><i style="background:var(--surface-3)"></i>Scheduled</span>@endif
            </div>
        </div>

        <dl class="pay-sum__facts">
            @php
                $facts = [
                    ['Purchase price', Fmt::money($p->total()), ''],
                    ['Paid to date', Fmt::money($p->paidAmount()), 'pos'],
                    ['Instalments left', $p->settled() ? 'None' : $remaining.' of '.$p->payments->count(), ''],
                    $next
                        ? ['Next due', Fmt::date($next->due_at), $p->overdue()->count() ? 'neg' : '']
                        : ['Status', 'Settled', 'pos'],
                ];
            @endphp
            @foreach ($facts as [$k, $v, $tone])
                <div class="pay-sum__fact">
                    <dt>{{ $k }}</dt>
                    <dd class="{{ $tone }}">{{ $v }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- ─────────────────────── overdue notice: what happens next ── --}}

    @if ($p->lateCount())
        @php $worst = $p->worstLateBy(); @endphp
        <div class="card" style="border-color:var(--sev-critical)">
            <div class="card__body row" style="gap:var(--s-4);align-items:flex-start">
                <span style="color:var(--sev-critical);flex:none;margin-top:2px">
                    <x-icon name="warn" size="18" />
                </span>
                <div class="grow">
                    <div class="semi">
                        {{ Fmt::money($p->lateAmount()) }} is past due across
                        {{ $p->lateCount() }} {{ $p->lateCount() === 1 ? 'instalment' : 'instalments' }}
                    </div>
                    <div class="t-sm t-2" style="margin-top:5px;line-height:1.6">
                        The oldest instalment is {{ $worst }} days late. Late instalments do not attract
                        a penalty on this plan, but handover cannot be scheduled until the balance
                        clears. If the timing is difficult, your agent can restructure the remaining
                        schedule — that is a normal request, not an exception.
                    </div>
                    <div class="row-2" style="margin-top:var(--s-3)">
                        <a class="btn btn--primary btn--sm"
                           href="{{ route('portal.support', ['topic' => 'payment-plan']) }}" wire:navigate>
                            <x-icon name="feedback" size="13" /><span>Request a revised plan</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ───────────────────────────────────────── everything else held ──
         Browsing encourages a second purchase, so a page that shows one and
         hides the rest would be quietly wrong for anyone who took it up. --}}

    @if ($awaiting->isNotEmpty() || $others->isNotEmpty())
        <div>
            <div class="pt-sec">
                <div>
                    <h2>My other properties</h2>
                    <p>
                        {{ $others->count() + $awaiting->count() }}
                        {{ ($others->count() + $awaiting->count()) === 1 ? 'more commitment' : 'more commitments' }}
                    </p>
                </div>
            </div>

            <div class="pgrid">
                @foreach ($awaiting as $c)
                    <div class="pcard">
                        <div class="pcard__art">
                            <x-property-art :property="$c->property" />
                            <span class="pcard__tag">Awaiting approval</span>
                        </div>
                        <div class="pcard__b">
                            <h3 class="pcard__t">{{ $c->property?->title ?? 'Property' }}</h3>
                            <span class="pcard__loc">{{ $c->property?->location }}</span>
                            <span class="pcard__price">{{ Fmt::money($c->value) }}</span>
                            <span class="pcard__per">
                                {{ $c->duration_months }} months · committed {{ Fmt::ago($c->committed_at) }}.
                                Nothing starts until this is approved.
                            </span>
                        </div>
                    </div>
                @endforeach

                @foreach ($others as $o)
                    @php $prog = $o->progress(); @endphp
                    <div class="pcard">
                        <div class="pcard__art">
                            <x-property-art :property="$o->property" />
                            @if ($o->settled())
                                <span class="pcard__tag">Fully paid</span>
                            @endif
                        </div>
                        <div class="pcard__b">
                            <h3 class="pcard__t">{{ $o->property?->title ?? 'Property' }}</h3>
                            <span class="pcard__loc">{{ $o->property?->location }}</span>
                            <span class="pcard__price">{{ Fmt::money($o->total()) }}</span>

                            <div style="margin-top:var(--s-2)">
                                <div class="row-2" style="justify-content:space-between;margin-bottom:5px">
                                    <span class="t-xs t-3">
                                        {{ $o->paid()->count() }} of {{ $o->payments->count() }} paid
                                    </span>
                                    <span class="t-xs semi">{{ Fmt::pct($prog, 0) }}</span>
                                </div>
                                <div class="jbar">
                                    <div class="jbar__f" style="width:{{ round($prog * 100, 1) }}%"></div>
                                </div>
                            </div>

                            @if ($o->lateCount())
                                <span class="pcard__per" style="color:var(--neg)">
                                    {{ Fmt::money($o->lateAmount()) }} past due on this one
                                </span>
                            @elseif ($o->next())
                                <span class="pcard__per">
                                    Next <b>{{ Fmt::money($o->next()->amount) }}</b>
                                    on {{ Fmt::dateShort($o->next()->due_at) }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────── schedule ── --}}

    <div>
        <div class="pt-sec">
            <div>
                <h2>Instalment schedule</h2>
                <p>{{ $p->payments->count() }} instalments on this purchase</p>
            </div>
        </div>

        <div class="card">
            <div class="tablewrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th><th class="right">Amount</th><th>Due</th>
                            <th>Status</th><th>Paid on</th><th>Method</th><th class="right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($p->payments as $pay)
                            @php
                                $st = $pay->liveStatus();
                                $late = in_array($st, ['overdue', 'due'], true);
                            @endphp
                            <tr class="{{ $st === 'overdue' ? 'is-flagged' : '' }}" wire:key="pp-{{ $pay->id }}">
                                <td><span class="num t-3">{{ $pay->instalment }}/{{ $pay->of_total }}</span></td>
                                <td class="right"><span class="num semi">{{ Fmt::money($pay->amount) }}</span></td>
                                <td>
                                    <div class="num {{ $late ? 'neg' : '' }}">{{ Fmt::date($pay->due_at) }}</div>
                                    @if ($late)
                                        <div class="t-xs neg">
                                            {{ $pay->lateBy() }} {{ $pay->lateBy() === 1 ? 'day' : 'days' }} late
                                        </div>
                                    @elseif ($st === 'scheduled')
                                        <div class="t-xs t-3">{{ Fmt::ago($pay->due_at) }}</div>
                                    @endif
                                </td>
                                <td><x-pay-status :status="$st" /></td>
                                <td><span class="num t-3">{{ $pay->paid_at ? Fmt::date($pay->paid_at) : '—' }}</span></td>
                                <td><span class="t-sm t-3">{{ $pay->method }}</span></td>
                                <td class="right">
                                    @if ($st === 'paid')
                                        <button class="btn btn--ghost btn--sm" wire:click="receipt({{ $pay->id }})">
                                            <x-icon name="download" size="13" /><span>Receipt</span>
                                        </button>
                                    @else
                                        <span class="t-4 t-sm">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ─────────────────────────────────────── how payments work ── --}}

    <div>
        <div class="pt-sec">
            <div><h2>How payments work</h2><p>The things people ask us most</p></div>
        </div>

        <div class="card">
            <div class="card__body faq">
                @foreach ([
                    'When is each instalment due?' =>
                        'Instalments fall roughly every 4–6 weeks from the contract date. Your exact dates are in the table above and never change without you agreeing to it.',
                    'What happens if I pay late?' =>
                        'Nothing punitive on this plan — there is no late fee. The only consequence is that handover cannot be scheduled until the balance clears, so a long delay moves your keys, not your price.',
                    'Can I pay early or pay more than the instalment?' =>
                        'Yes. Overpayments are applied to the next instalment automatically and shorten the plan. Tell your agent so the schedule is reissued to match.',
                    'Can the schedule be changed?' =>
                        'Yes, and it is a normal request. Ask through Support and your agent will propose a revised plan over the same total.',
                    'How do I get a receipt?' =>
                        'Every paid instalment has a Receipt button in the table above. Documents such as the deed and survey plan are in the Documents tab.',
                ] as $q => $a)
                    <details>
                        <summary>{{ $q }}</summary>
                        <p>{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </div>
</div>
