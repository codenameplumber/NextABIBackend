@php use App\Support\Fmt; @endphp

<div>
    <x-page-head title="My commission"
                 :sub="Fmt::money($earned).' earned across '.$rows->count().' '.($rows->count() === 1 ? 'sale' : 'sales')" />

    <x-page-note>
        The rate that was applied is shown against every line. **Rates differ per property and
        can change over time** — so a single blended percentage would leave you unable to check
        any individual figure.
    </x-page-note>

    <div class="grid g-3">
        <x-kpi label="Earned" :value="$earned" format="money" ico="finance"
               note="Across every approved sale" />
        <x-kpi label="Paid out" :value="$paid" format="money" ico="check"
               :note="$earned > 0 ? Fmt::pct($paid / $earned, 0).' of what you have earned' : 'Nothing yet'" />
        <x-kpi label="Owed to you" :value="$owed" format="money" ico="clock"
               :note="$owed > 0 ? 'Awaiting payout' : 'All settled'" />
    </div>

    @if ($rows->isEmpty())
        <x-empty title="No commission yet"
                 msg="Commission accrues the moment a commitment you brought in is approved." />
    @else
        <x-card title="Statement" sub="Every sale, with the rate that was applied">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th>Investor</th>
                            <th class="num">Sale value</th>
                            <th class="num">Rate</th>
                            <th class="num">Commission</th>
                            <th>Status</th>
                            <th>Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td class="semi">
                                    {{ $r->commitment?->property?->title ?? '—' }}
                                    <div class="t-xs t-3">{{ $r->commitment?->property?->ref }}</div>
                                </td>
                                <td>{{ $r->commitment?->customer?->name ?? '—' }}</td>
                                <td class="num">{{ Fmt::money($r->commitment?->value ?? 0) }}</td>
                                <td class="num">{{ number_format($r->rate * 100, 1) }}%</td>
                                <td class="num semi">{{ Fmt::money($r->amount) }}</td>
                                <td>
                                    <x-badge :kind="$r->statusKind()">{{ $r->statusLabel() }}</x-badge>
                                </td>
                                <td class="t-3">{{ $r->paid_at ? Fmt::dateShort($r->paid_at) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>
