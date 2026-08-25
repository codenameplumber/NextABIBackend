@php
    use App\Engine\InsightEngine;
    use App\Support\Fmt;

    $role = session('nexabi.role', auth()->user()->role);
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');

    $top = $feed[0] ?? null;
    $primary = $top?->primaryRecommendation();
@endphp

<div class="stack">

    <x-page-head :title="'Good '.$greeting"
                 :sub="app('nexabi.tenant')->name.' · '.InsightEngine::ROLES[$role]['label'].' view · as at '.Fmt::date(now())">
        <x-slot:actions>
            <a class="btn" href="{{ route('app.ask') }}" wire:navigate>
                <x-icon name="ask" size="15" /><span>Ask NexABI</span>
            </a>
            <a class="btn btn--ghost" href="{{ route('app.insights') }}" wire:navigate>
                <x-icon name="insight" size="15" /><span>All insights</span>
            </a>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        Read this page top to bottom. **The gold box is where to start** — then the money,
        then which parts of the business need attention.
    </x-page-note>

    {{-- ─────────────────────────────────────────────── 1. AI briefing ── --}}

    <div class="hero">
        <div class="hero__eyebrow">
            <x-icon name="insight" size="13" /><span>Where the business stands</span>
        </div>
        <p class="hero__text">{!! Fmt::rich($brief) !!}</p>
    </div>

    {{-- ───────────────────────────────────────── 2. the decision bar ──
         One thing. Not a list — the top of a dashboard is the most valuable
         space in the product, and a list of five is a list of none.        --}}

    @if ($top)
        <section class="decide">
            <div class="decide__top">
                <x-icon name="bolt" size="15" />
                <span class="decide__count">
                    {{ count($feed) }} {{ count($feed) === 1 ? 'finding needs' : 'findings need' }} a decision
                </span>
                @if ($counts['critical'])
                    <x-badge kind="critical" dot>{{ $counts['critical'] }} critical</x-badge>
                @endif
                @if ($impact > 0)
                    <span class="badge badge--ai" title="Sum of the best estimated impact per finding">
                        <span>{{ Fmt::moneyShort($impact) }} on the table</span>
                    </span>
                @endif
                <span class="spacer"></span>
                <a class="btn btn--sm btn--ghost" href="{{ route('app.insights') }}" wire:navigate>
                    <span>Review all</span><x-icon name="right" size="13" />
                </a>
            </div>

            <div class="decide__body">
                <div class="decide__lead">
                    <div class="decide__eyebrow">
                        Start here · {{ InsightEngine::AREAS[$top->area] ?? $top->area }}
                        · {{ Fmt::pct($top->confidence, 0) }} confidence
                    </div>
                    <div class="decide__title">{{ $top->title }}</div>
                    @if ($primary)
                        <div class="decide__why">{{ $primary->rationale }}</div>
                    @endif
                </div>

                @if ($primary)
                    <div class="decide__act">
                        <button class="btn btn--primary" title="{{ $primary->label }}"
                                wire:click="applyTop({{ $primary->id }})"
                                wire:loading.attr="disabled" wire:target="applyTop">
                            <x-icon name="bolt" size="15" />
                            <span wire:loading.remove wire:target="applyTop">
                                {{ mb_strlen($primary->label) > 34 ? 'Apply this' : $primary->label }}
                            </span>
                            <span wire:loading wire:target="applyTop">Applying…</span>
                        </button>
                        <div class="row-2">
                            @if ($primary->impact > 0)
                                <span class="badge badge--ai"><span>{{ Fmt::moneyShort($primary->impact) }}</span></span>
                            @endif
                            <x-badge>{{ $primary->effortLabel() }}</x-badge>
                        </div>
                        <a class="link t-sm" href="{{ route('app.insights', ['id' => $top->id]) }}" wire:navigate>
                            See the evidence
                        </a>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ─────────────────────────────────────────────────── 3. money ──
         Only four tiles. The rates that used to sit in a second row — win
         rate, collection ratio, days on market — live in their own modules
         and are pointed at by the strip below. Eight numbers at the top of a
         dashboard is not twice the information, it is half the attention. --}}

    <x-sec-label title="The money" note="Trailing 90 days" />

    <div class="grid g-4">
        <x-kpi label="Revenue" :value="$headline['revenue']" format="money"
               :delta="$headline['revenueChange']" compare="vs prior 90 days"
               ico="sales" help-key="revenue" spark-color="var(--c1)"
               :spark="array_column($sales['series'], 'value')"
               :href="route('app.sales')" />

        <x-kpi label="Open pipeline" :value="$headline['pipeline']" format="money"
               :note="Fmt::num($headline['openLeads']).' live leads'"
               ico="leads" help-key="pipeline" spark-color="var(--c3)"
               :spark="array_column($leads['series'], 'value')"
               :href="route('app.leads')" />

        <x-kpi label="Inventory value" :value="$headline['inventoryValue']" format="money"
               :note="Fmt::num($headline['available']).' available'"
               ico="property" help-key="inventory"
               :href="route('app.properties')" />

        <x-kpi label="Overdue receivables" :value="$headline['overdue']" format="money"
               :note="Fmt::pct($headline['collectionRatio'], 0).' collected'"
               ico="finance" help-key="overdue"
               :href="route('app.finance', ['filter' => 'overdue'])" />
    </div>

    {{-- ───────────────────────────────────── 4. where the problems are ──
         The one-glance read across every area. Without this, seven modules
         is a navigation problem rather than a picture.                     --}}

    <x-sec-label title="Where the problems are" note="Worst open finding per area · click to open" />

    <div class="health">
        @foreach (InsightEngine::AREAS as $key => $label)
            @php
                $entry = $worstByArea[$key] ?? null;
                $band = $entry['worst']->band ?? 'clear';
            @endphp
            <a class="health-cell is-{{ $band }}"
               href="{{ $entry ? route('app.insights', ['area' => $key]) : route('app.'.$key) }}"
               wire:navigate
               title="{{ $entry ? $entry['worst']->title : 'Nothing flagged in '.$label }}">
                @if ($entry)<span class="health-cell__n">{{ $entry['count'] }}</span>@endif
                <div class="health-cell__top">
                    <span class="health-cell__dot"></span>
                    <span class="health-cell__name">{{ $label }}</span>
                </div>
                <div class="health-cell__state clamp-2">
                    {{ $entry ? $entry['worst']->title : 'Nothing flagged' }}
                </div>
            </a>
        @endforeach
    </div>

    {{-- ──────────────────────────────────────────── 5. evidence + feed ── --}}

    <x-sec-label title="The evidence" note="Everything above, drawn from these" />

    <div class="dash-split">
        <div class="stack-4">

            <x-card title="Revenue by month" sub="Value of deals on the month they closed">
                <x-slot:actions>
                    <a class="btn btn--sm btn--ghost" href="{{ route('app.sales') }}" wire:navigate>
                        <span>Open sales</span>
                    </a>
                </x-slot:actions>

                <x-chart kind="line" size="lg" :payload="$charts['revenue']" />

                <x-chart-note>
                    The dashed tail is the current month, which is not finished yet —
                    read the level, not the drop.
                </x-chart-note>
            </x-card>

            <div class="grid g-2">
                <x-card title="Where leads are lost" sub="Every lead that ever reached each stage">
                    <x-chart kind="funnel" :payload="$charts['funnel']" />
                    <x-chart-note>
                        The percentage is the share that advanced from the stage above.
                        Anything under 50% is where to look first.
                    </x-chart-note>
                </x-card>

                <x-card title="Pipeline by source" sub="Open lead value, by where the lead came from">
                    <x-chart kind="hbar" :payload="$charts['bySource']" />
                    <x-chart-note>
                        Long bars are not automatically good — a big pipeline from a source
                        that rarely closes is capacity being spent, not revenue coming.
                    </x-chart-note>
                </x-card>
            </div>

            <x-card title="Inventory by location" sub="Units currently available to sell">
                <x-chart kind="bar" :payload="$charts['byLocation']" />
                <x-chart-note>
                    High inquiries against low stock is the signal that a segment is underpriced.
                </x-chart-note>
            </x-card>
        </div>

        {{-- ---- right: the full feed ---- --}}

        <div class="stack-4">
            <div class="between">
                <div>
                    <h3>Needs your attention</h3>
                    <div class="t-sm t-3">Ranked for {{ InsightEngine::ROLES[$role]['label'] }}</div>
                </div>
                <a class="btn btn--sm btn--ghost" href="{{ route('app.insights') }}" wire:navigate>
                    <span>See all</span>
                </a>
            </div>

            <div class="feed">
                @forelse (array_slice($feed, 0, 4) as $insight)
                    <livewire:insight-card :insight="$insight" :key="'dash-'.$insight->id" />
                @empty
                    <x-empty title="Nothing flagged" ico="check"
                             msg="Every detector came back clean against the current data. Import new data or wait for the next scheduled run to see this change." />
                @endforelse

                @if (count($feed) > 4)
                    <a class="factor" href="{{ route('app.insights') }}" wire:navigate>
                        <x-icon name="insight" size="15" />
                        <span class="grow">{{ count($feed) - 4 }} more findings</span>
                        <x-icon name="right" size="14" />
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ────────────────────────────────────────────── 6. what you did ── --}}

    @if ($log->isNotEmpty())
        <x-card title="What you changed" sub="Each accepted recommendation, with what actually moved" flush>
            <x-slot:actions>
                <a class="btn btn--sm btn--ghost" href="{{ route('app.activity') }}" wire:navigate>
                    <span>Full log</span>
                </a>
            </x-slot:actions>

            @foreach ($log as $a)
                <div class="log-item">
                    <div class="log-item__dot"><x-icon name="check" size="14" /></div>
                    <div class="grow">
                        <div class="semi">{{ $a->label }}</div>
                        <div class="t-sm t-3">{{ $a->message }}</div>
                        @if ($a->before || $a->after)
                            <div class="log-item__diff">
                                <b>{{ $a->before ?: '—' }}</b> → <b>{{ $a->after ?: '—' }}</b>
                            </div>
                        @endif
                    </div>
                    <span class="t-xs t-4 nowrap">{{ Fmt::date($a->created_at) }}</span>
                </div>
            @endforeach
        </x-card>
    @endif
</div>
