@php
    use App\Engine\InsightEngine;
    use App\Support\Fmt;
@endphp

<div class="stack">

    <x-page-head title="Action Log"
                 :sub="$count ? $count.' actions · '.Fmt::num($affected).' records changed' : 'Nothing yet'">
        @if ($count)
            <x-slot:actions>
                <button class="btn btn--ghost" wire:click="export">
                    <x-icon name="download" size="15" /><span>Export JSON</span>
                </button>
            </x-slot:actions>
        @endif
    </x-page-head>

    <x-page-note>
        Every recommendation you accepted, and exactly what it changed in the data.
        **This is the proof the loop closed** rather than the card just disappearing.
    </x-page-note>

    @if (! $count)
        <x-empty title="No actions yet" ico="bolt"
                 msg="Accept a recommendation from any insight card and it will be recorded here with a before/after diff. That diff is the evidence the action changed real data rather than just dismissing a card.">
            <a class="btn btn--primary" href="{{ route('app.insights') }}" wire:navigate>
                <span>Go to insights</span>
            </a>
        </x-empty>
    @else
        <div class="grid g-4">
            <x-kpi label="Actions taken" :value="$count" format="count" ico="bolt" />
            <x-kpi label="Records changed" :value="$affected" format="count" ico="reports" />
            <x-kpi label="Estimated impact" :value="$impact" format="money" ico="sales" />
            <x-kpi label="Last action" :value="$last ? Fmt::ago($last->created_at) : '—'"
                   format="raw" ico="clock" />
        </div>

        <x-card title="History" :sub="$count.' entries, newest first'" flush>
            @foreach ($log as $a)
                <div class="log-item" wire:key="log-{{ $a->id }}">
                    <div class="log-item__dot"><x-icon name="check" size="14" /></div>

                    <div class="grow">
                        <div class="row-2 wrap" style="margin-bottom:3px">
                            <span class="semi">{{ $a->label }}</span>
                            @if ($a->area)
                                <x-badge>{{ InsightEngine::AREAS[$a->area] ?? $a->area }}</x-badge>
                            @endif
                            <span class="badge mono" title="Action type"><span>{{ $a->action_type }}</span></span>
                        </div>

                        <div class="t-sm t-3">{{ $a->message }}</div>
                        @if ($a->detail)<div class="t-sm t-3">{{ $a->detail }}</div>@endif

                        @if ($a->hasDiff())
                            <div class="log-item__diff">
                                <b>{{ $a->before }}</b><span> → </span><b>{{ $a->after }}</b>
                            </div>
                        @endif

                        @if ($a->insight)
                            <div class="t-xs t-4" style="margin-top:4px">From: {{ $a->insight->title }}</div>
                        @endif
                    </div>

                    <div class="col" style="align-items:flex-end;gap:4px">
                        @if ($a->impact > 0)
                            <span class="badge badge--ai"><span>{{ Fmt::moneyShort($a->impact) }}</span></span>
                        @endif
                        <span class="t-xs t-4 nowrap">{{ Fmt::date($a->created_at) }}</span>
                    </div>
                </div>
            @endforeach

            @if ($log->hasPages())
                <x-slot:foot>{{ $log->links() }}</x-slot:foot>
            @endif
        </x-card>
    @endif

    {{-- ---------------------------------------------- underlying events -- --}}

    <x-card title="Business event stream" sub="The raw activity spine the metrics layer reads from" flush>
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr><th>Event</th><th>Entity</th><th>Agent</th><th>Note</th><th class="right">When</th></tr>
                </thead>
                <tbody>
                    @foreach ($events as $e)
                        <tr wire:key="ev-{{ $e->id }}">
                            <td><span class="cell-primary">{{ Fmt::titleCase($e->type) }}</span></td>
                            <td><span class="t-2 mono t-sm">{{ $e->entity_type }}#{{ $e->entity_id }}</span></td>
                            <td><span class="t-2">{{ $e->agent->name ?? '—' }}</span></td>
                            <td><span class="t-3 t-sm">{{ $e->note ?: '—' }}</span></td>
                            <td class="right"><span class="num t-3">{{ Fmt::date($e->at) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</div>
