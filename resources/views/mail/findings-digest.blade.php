@php
    use App\Engine\InsightEngine;
    use App\Support\Fmt;

    $band = [
        'critical' => '#C0392B',
        'high' => '#D97B2B',
        'medium' => '#C9A227',
        'low' => '#8B8578',
    ];
@endphp

<x-mail.layout
    :eyebrow="$tenant->name.' · '.now()->format('j M Y')"
    :heading="count($insights)
        ? count($insights).' '.(count($insights) === 1 ? 'thing needs' : 'things need').' a decision'
        : 'Nothing needs a decision today'"
    :preheader="strip_tags(str_replace('**', '', $brief))"
    cta="Open the console"
    :cta-url="route('app.dashboard')">

    <p style="margin:0 0 18px;">
        {!! preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', e($brief)) !!}
    </p>

    @if (count($insights))
        @if ($impact > 0)
            <div style="background:#FDF6E4;border:1px solid #F0DCA8;border-radius:10px;padding:12px 16px;margin:0 0 18px;font:400 14px/1.5 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#5C4A12;">
                Roughly <b>{{ Fmt::moneyShort($impact) }}</b> of estimated impact is on the table
                across these findings.
            </div>
        @endif

        @foreach (array_slice($insights, 0, 5) as $i)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="border:1px solid #EDE9E2;border-left:3px solid {{ $band[$i->band] ?? '#8B8578' }};border-radius:8px;margin:0 0 10px;">
                <tr>
                    <td style="padding:14px 16px;">
                        <div style="font:600 10px/1.3 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;letter-spacing:.09em;text-transform:uppercase;color:{{ $band[$i->band] ?? '#8B8578' }};padding-bottom:6px;">
                            {{ $i->band }} · {{ InsightEngine::AREAS[$i->area] ?? $i->area }}
                            · {{ Fmt::pct($i->confidence, 0) }} confidence
                        </div>
                        <div style="font:600 15px/1.4 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#171614;">
                            {{ $i->title }}
                        </div>
                        @if ($rec = $i->primaryRecommendation())
                            <div style="padding-top:8px;font:400 13px/1.55 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#6B665C;">
                                Suggested: {{ $rec->label }}@if ($rec->impact > 0) · est. {{ Fmt::moneyShort($rec->impact) }}@endif
                            </div>
                        @endif
                    </td>
                </tr>
            </table>
        @endforeach

        @if (count($insights) > 5)
            <p style="margin:12px 0 0;color:#8B8578;font-size:13px;">
                {{ count($insights) - 5 }} more in the console.
            </p>
        @endif
    @else
        <p style="margin:0;">
            Every detector came back clean against today's data. That is a real result rather
            than a quiet failure — the run is logged and the next one is in fifteen minutes.
        </p>
    @endif

    <x-slot:footer>
        Ranked for {{ InsightEngine::ROLES[$user->role]['label'] ?? 'your seat' }}.
        Sent because you are on the {{ $tenant->name }} team.
    </x-slot:footer>
</x-mail.layout>
