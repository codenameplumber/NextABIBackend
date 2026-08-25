@php use App\Support\Fmt; @endphp

<x-mail.layout
    eyebrow="New request"
    :heading="$ticket->customer->name.' needs something'"
    :preheader="$ticket->topic.' — '.Str::limit($ticket->body, 90)"
    cta="Open the console"
    :cta-url="route('app.customers', ['q' => $ticket->customer->name])">

    <p style="margin:0 0 16px;">
        <b>{{ $ticket->customer->name }}</b> raised this through the client portal.
        Reply to this email and it goes straight back to them.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background:#FAF8F4;border:1px solid #EDE9E2;border-radius:10px;margin:0 0 16px;">
        <tr>
            <td style="padding:16px 18px;">
                <div style="font:600 11px/1.3 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#8B8578;padding-bottom:8px;">
                    {{ $ticket->topic }} · {{ $ticket->reference }}
                </div>
                <div style="font:400 15px/1.62 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#171614;">
                    {{ $ticket->body }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Context the agent would otherwise have to go and look up. Whether this
         person is behind on payments changes how the reply should read. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 16px;font:400 13px/1.7 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;">
        @foreach ([
            'Lifetime value' => Fmt::money($ticket->customer->ltv),
            'Segment' => Fmt::titleCase($ticket->customer->segment ?? '—'),
            'Outstanding' => Fmt::money($outstanding ?? 0),
            'Raised' => $ticket->created_at->format('j M Y, H:i'),
        ] as $k => $v)
            <tr>
                <td style="padding:3px 0;color:#8B8578;width:44%;">{{ $k }}</td>
                <td style="padding:3px 0;color:#171614;font-weight:600;">{{ $v }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin:0;color:#8B8578;font-size:13px;">
        Unanswered after two working days, this escalates to the operations team.
    </p>

    <x-slot:footer>
        Sent to you because {{ $ticket->customer->name }} is your client.
    </x-slot:footer>
</x-mail.layout>
