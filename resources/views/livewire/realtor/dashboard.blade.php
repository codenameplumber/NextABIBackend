@php use App\Support\Fmt; @endphp

<div>
    <section class="pt-banner">
        <div class="pt-banner__eyebrow">
            <x-icon name="agent" size="13" />
            <span>Hello, {{ $realtor->firstName() }}</span>
        </div>
        <h1 class="pt-banner__h">
            @if ($owed > 0)
                You have {{ Fmt::money($owed) }} in commission owed.
            @elseif ($pending > 0)
                {{ $pending }} of your {{ $pending === 1 ? 'commitment is' : 'commitments are' }} awaiting approval.
            @elseif ($investorCount > 0)
                {{ $investorCount }} {{ $investorCount === 1 ? 'investor' : 'investors' }} on your book.
            @else
                Let us get you your first investor.
            @endif
        </h1>
        <p class="pt-banner__p">
            Share your link and anyone who registers through it is yours automatically —
            no code for them to remember and nothing for you to file.
        </p>

        {{-- The link is the job. It goes at the top, copyable in one click. --}}
        <div class="row-2 wrap" style="margin-top:var(--s-4);gap:var(--s-2)">
            <button class="btn btn--primary" data-copy="{{ $realtor->registrationLink() }}">
                <x-icon name="plus" size="15" /><span>Copy my registration link</span>
            </button>
            <button class="btn btn--ghost" data-copy="{{ $realtor->questionnaireLink() }}">
                <span>Copy my enquiry form link</span>
            </button>
            <a class="btn btn--ghost" href="{{ route('realtor.materials') }}" wire:navigate>
                <x-icon name="marketing" size="15" /><span>Materials</span>
            </a>
        </div>
    </section>

    <div class="page-note pt-note">
        <x-icon name="info" size="15" />
        <span>
            Your code is <b class="mono">{{ $realtor->code }}</b>. It is in every link and every
            piece of material you download, so you never have to type it.
        </span>
    </div>

    {{-- ────────────────────────────────────────────────── the numbers ── --}}

    <div class="grid g-4" style="margin-top:var(--s-5)">
        <x-kpi label="Investors" :value="$investorCount" format="count" ico="customers"
               :note="$leadCount.' leads captured'" />
        <x-kpi label="Sales value" :value="$salesValue" format="money" ico="sales"
               :note="$live.' paying · '.$completed.' settled'" />
        <x-kpi label="Commission earned" :value="$earned" format="money" ico="finance"
               :note="Fmt::money($paid).' paid out'" />
        <x-kpi label="Owed to you" :value="$owed" format="money" ico="finance"
               :note="$owed > 0 ? 'Awaiting payout' : 'All settled'" />
    </div>

    {{-- ───────────────────────────────────────────────── recent work ── --}}

    <div class="pt-sec" style="margin-top:var(--s-7)">
        <div>
            <h2>Recent commitments</h2>
            <p>What your investors have committed to</p>
        </div>
        <a class="btn btn--ghost btn--sm" href="{{ route('realtor.commission') }}" wire:navigate>
            <span>Full statement</span><x-icon name="right" size="13" />
        </a>
    </div>

    @if ($recent->isEmpty())
        <x-empty title="Nothing yet"
                 msg="Share your registration link, and anyone who signs up through it appears here." />
    @else
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Investor</th>
                        <th>Property</th>
                        <th class="num">Value</th>
                        <th class="num">Term</th>
                        <th>Status</th>
                        <th>Committed</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recent as $c)
                        <tr>
                            <td class="semi">{{ $c->customer?->name ?? '—' }}</td>
                            <td>
                                {{ $c->property?->title ?? '—' }}
                                <div class="t-xs t-3">{{ $c->property?->location }}</div>
                            </td>
                            <td class="num">{{ Fmt::money($c->value) }}</td>
                            <td class="num">{{ $c->duration_months ? $c->duration_months.'m' : '—' }}</td>
                            <td>
                                <x-badge :kind="match($c->status) {
                                    'completed' => 'pos',
                                    'active', 'approved' => 'info',
                                    'pending' => 'medium',
                                    default => 'low',
                                }">{{ $c->statusLabel() }}</x-badge>
                            </td>
                            <td class="t-3">{{ Fmt::dateShort($c->committed_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
