@php use App\Support\Fmt; @endphp

<x-mail.layout
    eyebrow="Payment due"
    :heading="$payments->count() === 1
        ? 'One instalment is waiting'
        : $payments->count().' instalments are waiting'"
    :preheader="Fmt::money($total).' outstanding on your property purchase.'"
    cta="View your schedule"
    :cta-url="route('portal.payments')">

    <p style="margin:0 0 14px;">Hello {{ $customer->firstName() }},</p>

    @php
        // Composed in PHP: an inline @if that straddles a line break inside a
        // sentence is a Blade parse error, and the clause reads better assembled
        // than it does split across two directives anyway.
        $line = '<b>'.Fmt::money($total).'</b> is currently outstanding on your purchase';
        $line .= $worst > 0
            ? ', the oldest by <b>'.$worst.' '.($worst === 1 ? 'day' : 'days').'</b>.'
            : '.';
    @endphp

    <p style="margin:0 0 16px;">{!! $line !!}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="border:1px solid #EDE9E2;border-radius:10px;overflow:hidden;margin:0 0 18px;">
        <tr style="background:#FAF8F4;">
            <td style="padding:9px 14px;font:600 11px/1.3 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;letter-spacing:.07em;text-transform:uppercase;color:#8B8578;">Instalment</td>
            <td style="padding:9px 14px;font:600 11px/1.3 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;letter-spacing:.07em;text-transform:uppercase;color:#8B8578;">Due</td>
            <td align="right" style="padding:9px 14px;font:600 11px/1.3 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;letter-spacing:.07em;text-transform:uppercase;color:#8B8578;">Amount</td>
        </tr>
        @foreach ($payments as $p)
            <tr style="border-top:1px solid #EDE9E2;">
                <td style="padding:11px 14px;font:400 14px/1.4 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#413D36;">
                    {{ $p->instalment }} of {{ $p->of_total }}
                </td>
                <td style="padding:11px 14px;font:400 14px/1.4 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:{{ $p->lateBy() > 0 ? '#C0392B' : '#413D36' }};">
                    {{ Fmt::date($p->due_at) }}
                    @if ($p->lateBy() > 0)
                        <span style="font-size:12px;"> · {{ $p->lateBy() }}d late</span>
                    @endif
                </td>
                <td align="right" style="padding:11px 14px;font:600 14px/1.4 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#171614;">
                    {{ Fmt::money($p->amount) }}
                </td>
            </tr>
        @endforeach
    </table>

    {{-- Said plainly, because it is true and because a threat nobody intends
         to carry out is the fastest way to lose a customer's trust. --}}
    <p style="margin:0 0 14px;">
        There is no late fee on this plan. The only consequence of a delay is that
        handover cannot be scheduled until the balance clears — so a long delay moves
        your keys, not your price.
    </p>

    <p style="margin:0;">
        If the timing is difficult, your agent can restructure the remaining schedule over
        the same total. That is a routine request, not an exception — just ask through the
        Support tab.
    </p>

    <x-slot:footer>
        Sent because you have an outstanding balance with {{ config('app.name') }}.
    </x-slot:footer>
</x-mail.layout>
