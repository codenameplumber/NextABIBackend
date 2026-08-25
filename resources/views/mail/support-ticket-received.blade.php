<x-mail.layout
    eyebrow="Request received"
    heading="We have this, and someone is on it"
    preheader="Your agent has been notified and will reply within one working day."
    cta="View it in your portal"
    :cta-url="route('portal.support')">

    <p style="margin:0 0 14px;">
        Hello {{ $ticket->customer->firstName() }},
    </p>

    <p style="margin:0 0 16px;">
        Your request reached
        {{ $ticket->agent?->name ? $ticket->agent->name.', your agent' : 'your agent' }}
        and is logged as <b>{{ $ticket->reference }}</b>. You should hear back within one
        working day. If two working days pass without a reply, it escalates to the
        operations team automatically — you do not need to chase it.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background:#FAF8F4;border:1px solid #EDE9E2;border-radius:10px;margin:0 0 16px;">
        <tr>
            <td style="padding:16px 18px;">
                <div style="font:600 11px/1.3 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#8B8578;padding-bottom:8px;">
                    What you sent us · {{ $ticket->topic }}
                </div>
                <div style="font:400 15px/1.62 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#171614;">
                    {{ $ticket->body }}
                </div>
            </td>
        </tr>
    </table>

    <p style="margin:0;color:#8B8578;font-size:13px;">
        You can add to this or check its status any time in the Support tab of your portal.
    </p>

    <x-slot:footer>
        Sent because you raised {{ $ticket->reference }} on your account.
    </x-slot:footer>
</x-mail.layout>
