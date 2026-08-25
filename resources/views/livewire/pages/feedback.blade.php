@php use App\Support\Fmt; @endphp

<div class="stack">

    <x-page-head title="Feedback"
                 :sub="number_format($fb['avgRating'], 2).' average over '.Fmt::num($fb['window']).' reviews in the last 60 days'">
        <x-slot:actions>
            <a class="btn btn--ghost"
               href="{{ route('app.ask', ['q' => 'What are customers complaining about?']) }}" wire:navigate>
                <x-icon name="ask" size="15" /><span>Ask</span>
            </a>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        What customers actually said. One complaint is noise; **the same theme thirty times is
        an operational defect** — that is what the themes panel is for.
    </x-page-note>

    <div class="grid g-4">
        <x-kpi label="Average rating" :value="$fb['avgRating']" format="x" ico="feedback"
               help-key="satisfaction" :delta="$fb['ratingChange'] / 5"
               :note="'was '.number_format($fb['prevRating'], 2)" />

        <x-kpi label="Reviews · 60 days" :value="$fb['window']" format="count" ico="reports"
               :note="Fmt::num($fb['total']).' all-time'" />

        <x-kpi label="Negative" :value="$fb['negative']" format="count" ico="warn" invert
               :note="Fmt::pct($fb['negativeRate'], 0).' of reviews'" />

        <x-kpi label="Top theme" :value="$fb['tags'][0]['count'] ?? 0" format="count" ico="filter"
               :note="$fb['tags'][0]['key'] ?? '—'" />
    </div>

    <livewire:area-insights area="feedback" />

    {{-- ------------------------------------------------------- themes -- --}}

    <div class="grid g-2">
        <x-card title="Rating distribution" sub="Last 60 days">
            <x-chart kind="rating" :payload="$charts['distribution']" />
        </x-card>

        <x-card title="Recurring themes" sub="Tag frequency in the last 60 days versus the 60 before">
            <div class="tag-cloud">
                @foreach (array_slice($fb['tags'], 0, 12) as $t)
                    @php
                        // "Hot" means rising fast AND mostly attached to bad
                        // reviews. Either alone is not yet a problem.
                        $hot = $t['change'] > 0.5 && $t['negative'] > $t['count'] * 0.4;
                    @endphp
                    <button class="tag-pill {{ $tag === $t['key'] ? 'is-active' : '' }} {{ $hot ? 'is-hot' : '' }}"
                            wire:click="toggleTag(@js($t['key']))">
                        <span>{{ $t['key'] }}</span>
                        <b>{{ $t['count'] }}</b>
                        @if ($t['prev'] > 0)
                            <span class="t-xs {{ $t['change'] > 0 ? 'neg' : 'pos' }}">
                                {{ Fmt::delta($t['change'], 0) }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>

            <x-chart-note>
                The percentage is the change against the previous 60 days. A theme in gold is both
                rising and mostly attached to one- and two-star reviews.
            </x-chart-note>
        </x-card>
    </div>

    {{-- ------------------------------------------------------ reviews -- --}}

    <section class="card">
        <div class="card__head">
            <div>
                <div class="card__title">Reviews</div>
                <div class="card__sub">{{ Fmt::num($rows->total()) }} of {{ Fmt::num($fb['total']) }}</div>
            </div>
        </div>

        <div class="toolbar">
            <input class="input" type="search" placeholder="Search comments…"
                   style="min-width:220px" wire:model.live.debounce.300ms="q">

            <select class="input" wire:model.live="tag">
                <option value="">All themes</option>
                @foreach ($fb['tags'] as $t)
                    <option value="{{ $t['key'] }}">{{ $t['key'] }}</option>
                @endforeach
            </select>

            <select class="input" wire:model.live="rating">
                <option value="">All ratings</option>
                <option value="low">1–2 stars</option>
                <option value="mid">3 stars</option>
                <option value="high">4–5 stars</option>
            </select>

            <div class="spacer"></div>
        </div>

        <div>
            @forelse ($rows as $r)
                <div class="log-item" wire:key="fb-{{ $r->id }}">
                    <x-avatar :name="$r->customer->name ?? '?'" size="sm" />
                    <div class="grow">
                        <div class="row-2" style="margin-bottom:4px">
                            <span class="semi t-sm">{{ $r->customer->name ?? 'Customer' }}</span>
                            <span style="font-size:var(--t-sm);color:var({{ $r->rating >= 4 ? '--pos' : ($r->rating <= 2 ? '--neg' : '--text-3') }})">
                                {{ str_repeat('★', $r->rating).str_repeat('☆', 5 - $r->rating) }}
                            </span>
                            <span class="t-xs t-4">{{ $r->channel }} · {{ Fmt::date($r->created_at) }}</span>
                        </div>

                        <div class="quote">{{ $r->text }}</div>

                        <div class="row-2" style="margin-top:7px">
                            @foreach ($r->tags ?? [] as $t)
                                <button class="badge {{ $t === $tag ? 'badge--ai' : '' }}"
                                        style="cursor:pointer" wire:click="toggleTag(@js($t))">
                                    <span>{{ $t }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @if ($r->agent)
                        <span class="t-xs t-4 nowrap">{{ $r->agent->name }}</span>
                    @endif
                </div>
            @empty
                <x-empty title="Nothing here" ico="filter" msg="No reviews match those filters." />
            @endforelse
        </div>

        @if ($rows->hasPages())
            <div class="card__foot">{{ $rows->links() }}</div>
        @endif
    </section>
</div>
