@php
    use App\Engine\InsightEngine;
    use App\Livewire\Pages\Reports;
    use App\Support\Fmt;
@endphp

<div class="stack">

    <x-page-head title="Reports"
                 :sub="'Generated from live data as at '.Fmt::date(now()).' — every figure recomputes when the data changes'">
        <x-slot:actions>
            <button class="btn btn--ghost" onclick="window.print()">
                <x-icon name="download" size="15" /><span>Print</span>
            </button>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        Written summaries generated from the same live figures the dashboard reads — so
        **a report can never contradict a screen**.
    </x-page-note>

    <div class="chips">
        @foreach (Reports::REPORTS as $key => $r)
            <button class="chip {{ $report === $key ? 'is-active' : '' }}"
                    wire:click="setReport('{{ $key }}')">{{ $r['label'] }}</button>
        @endforeach
    </div>

    <div class="stack-4">

        <div class="hero">
            <div class="hero__eyebrow">
                <x-icon name="insight" size="13" />
                <span>Generated summary · {{ $spec['title'] }}</span>
            </div>
            <p class="hero__text">{!! Fmt::rich($summary) !!}</p>
        </div>

        <div class="card">
            <div class="stat-row">
                @foreach ($stats as $k => $v)
                    <div class="stat">
                        <span class="stat__k">{{ $k }}</span>
                        <span class="stat__v">{{ $v }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <x-card :title="$charts['main']['title']" :sub="$charts['main']['sub'] ?? null">
            <x-chart :kind="$charts['main']['kind']" :payload="$charts['main']['payload']" />
        </x-card>

        @if (isset($charts['second']))
            <x-card :title="$charts['second']['title']" :sub="$charts['second']['sub'] ?? null">
                <x-chart :kind="$charts['second']['kind']" :payload="$charts['second']['payload']" />
            </x-card>
        @endif

        {{-- Findings appendix — every report closes with the open risks. A
             summary that reads well and omits what is wrong is a brochure. --}}
        @if (count($insights))
            <x-card title="Open findings"
                    :sub="count($insights).' matters flagged by the detectors at the time of generation'"
                    flush>
                <div class="tablewrap">
                    <table class="table">
                        <thead>
                            <tr><th>Severity</th><th>Area</th><th>Finding</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($insights as $i)
                                <tr wire:key="rep-{{ $i->id }}">
                                    <td><x-badge :kind="$i->band" dot>{{ $i->band }}</x-badge></td>
                                    <td><span class="t-2">{{ InsightEngine::AREAS[$i->area] ?? $i->area }}</span></td>
                                    <td><span class="cell-primary">{{ $i->title }}</span></td>
                                    <td class="right">
                                        <a class="btn btn--sm btn--ghost"
                                           href="{{ route('app.insights', ['id' => $i->id]) }}" wire:navigate>
                                            <span>Open</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    </div>
</div>
