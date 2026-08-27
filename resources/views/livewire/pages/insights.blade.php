@php
    use App\Engine\InsightEngine;
    use App\Support\Fmt;
@endphp

<div class="stack">

    <x-page-head title="AI Insights"
                 :sub="count($all).' findings ranked for '.$roleLabel.' · roughly '.Fmt::moneyShort($impact).' of estimated impact on the table'">
        <x-slot:actions>
            <a class="btn btn--ghost" href="{{ route('app.ask', ['q' => 'What should I do next?']) }}" wire:navigate>
                <x-icon name="ask" size="15" /><span>Ask about these</span>
            </a>
            <button class="btn btn--ghost" wire:click="rerun" wire:loading.attr="disabled" wire:target="rerun">
                <x-icon name="reset" size="15" />
                <span wire:loading.remove wire:target="rerun">Re-run detectors</span>
                <span wire:loading wire:target="rerun">Running…</span>
            </button>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        Every problem NexABI has found across the business, worst first. Each one explains itself
        in plain language, links to the rows behind it, and comes with **a button that actually
        fixes it**.
    </x-page-note>

    {{-- ------------------------------------------------------- filters -- --}}

    <div class="feed-filters">
        <div class="seg">
            <button class="seg__btn {{ $band === 'all' ? 'is-active' : '' }}"
                    wire:click="setBand('all')">All {{ count($all) }}</button>
            <button class="seg__btn {{ $band === 'critical' ? 'is-active' : '' }}"
                    wire:click="setBand('critical')">Critical {{ $counts['critical'] }}</button>
            <button class="seg__btn {{ $band === 'high' ? 'is-active' : '' }}"
                    wire:click="setBand('high')">High {{ $counts['high'] }}</button>
        </div>

        <span class="vdivider"></span>

        <div class="chips">
            <button class="chip {{ $area === 'all' ? 'is-active' : '' }}"
                    wire:click="clearFilters">Every area</button>
            @foreach (InsightEngine::AREAS as $key => $label)
                @continue(($counts['byArea'][$key] ?? 0) === 0)
                <button class="chip {{ $area === $key ? 'is-active' : '' }}"
                        wire:click="setArea('{{ $key }}')">{{ $label }} {{ $counts['byArea'][$key] }}</button>
            @endforeach
        </div>
    </div>

    {{-- ---------------------------------------------------------- feed -- --}}

    @if ($shown->isEmpty())
        <x-empty :ico="count($all) ? 'filter' : 'check'"
                 :title="count($all) ? 'Nothing matches those filters' : 'No findings'"
                 :msg="count($all)
                    ? 'Clear the filters to see the rest.'
                    : 'Every detector returned clean against the current data. That is a genuine result — import new data and it will change.'">
            @if (count($all))
                <button class="btn btn--ghost" wire:click="clearFilters">Clear filters</button>
            @endif
        </x-empty>
    @else
        <div class="feed">
            @foreach ($shown as $insight)
                <div @if((string) $insight->id === $id) style="outline:2px solid var(--brand-500);outline-offset:2px;border-radius:var(--r-lg)" @endif>
                    {{-- The one screen that carries an executable action. You
                         arrive here to decide something; everywhere else a
                         finding is context for the table beneath it. --}}
                    <livewire:insight-card :insight="$insight" :show-actions="true"
                                           :key="'feed-'.$insight->id" />
                </div>
            @endforeach
        </div>
    @endif

    {{-- ------------------------------------------ operational limits -- --}}

    <x-card title="Operational limits"
            sub="Hard tripwires checked on every recompute — not findings, just lines you said you would not cross"
            flush>
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:44px"></th>
                        <th>Limit</th>
                        <th class="right">Now</th>
                        <th class="right">Threshold</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($limits as $l)
                        <tr class="{{ $l['breached'] ? 'is-flagged' : '' }}">
                            <td>
                                <span style="width:8px;height:8px;border-radius:50%;display:block;
                                             background:var({{ $l['breached'] ? '--sev-critical' : '--pos' }})"></span>
                            </td>
                            <td><span class="cell-primary">{{ $l['label'] }}</span></td>
                            <td class="right">
                                <span class="num {{ $l['breached'] ? 'neg semi' : '' }}">{{ $l['value'] }}</span>
                            </td>
                            <td class="right"><span class="num t-3">{{ $l['limit'] }}</span></td>
                            <td class="right">
                                <a class="btn btn--sm btn--ghost" href="{{ $l['route'] }}" wire:navigate>
                                    <span>Open</span>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- --------------------------------------- how this is ranked -- --}}

    <div class="card">
        <div class="card__body t-sm t-3">
            <div class="row-2" style="margin-bottom:8px">
                <x-icon name="info" size="14" />
                <b class="t-2">How this ranking works</b>
            </div>
            <div style="line-height:1.7">
                Each finding is scored
                <span class="mono t-2">severity × confidence × role weight × log(impact)</span>.
                Severity measures how far past the baseline the condition sits; confidence is gated
                on sample size, so a dramatic finding drawn from twelve records will not outrank a
                certain one drawn from four hundred. Switching role in the top bar re-ranks this
                list — it does not filter it, because a finance risk does not stop existing when a
                sales manager signs in.
            </div>
        </div>
    </div>
</div>
