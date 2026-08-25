@php
    use App\Engine\InsightEngine;
    use App\Support\Fmt;
@endphp

<div class="stack">

    <x-page-head title="Ask NexABI"
                 sub="Ask about your business in plain language. Every answer is computed from live data at the moment you ask." />

    <x-page-note>
        Ask the way you would ask a colleague. Every answer is computed from live data
        **at the moment you ask** — nothing here is pre-written.
    </x-page-note>

    {{-- ---------------------------------------------------------- input -- --}}

    <div class="ask">
        <form class="ask__input" wire:submit="submit">
            <x-icon name="ask" size="18" />
            <input type="text" wire:model="draft" autocomplete="off" autofocus
                   placeholder="e.g. why is our conversion falling?">
            <button class="btn btn--primary btn--sm" type="submit"
                    wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Ask</span>
                <span wire:loading wire:target="submit">Reading…</span>
            </button>
        </form>

        <div class="t-sm t-3" style="margin:12px 0 8px">Try one of these</div>
        <div class="chips">
            @foreach ($suggestions as $s)
                <button class="chip" wire:click="submit(@js($s))">{{ $s }}</button>
            @endforeach
        </div>
    </div>

    {{-- --------------------------------------------------------- answers -- --}}

    <div class="stack-4">
        @foreach ($answers as $index => $a)
            <div class="stack-2" wire:key="ans-{{ $index }}">

                <div class="ask-q">
                    <x-icon name="right" size="13" />
                    <b class="t-2">{{ $a['question'] }}</b>
                </div>

                <div class="ask-answer">
                    <div class="ask-answer__head">
                        <div class="grow">
                            <div class="ask-answer__value">{{ $a['headline'] ?? '—' }}</div>
                            @if ($a['sub'])<div class="ask-answer__sub">{{ $a['sub'] }}</div>@endif
                        </div>
                        <div class="col" style="align-items:flex-end;gap:8px">
                            @if ($a['change'] !== null)
                                <x-delta :value="$a['change']" />
                            @endif
                            <span class="badge {{ $a['confidence'] >= 0.8 ? 'badge--pos' : ($a['confidence'] >= 0.5 ? '' : 'badge--neg') }}"
                                  title="How much the engine trusts this answer">
                                <span>{{ Fmt::pct($a['confidence'], 0) }} confidence</span>
                            </span>
                        </div>
                    </div>

                    <div class="ask-answer__body">
                        @if ($a['narrative'])
                            <p class="ask-answer__narrative">{!! Fmt::rich($a['narrative']) !!}</p>
                        @endif

                        @if ($a['chart'])
                            <x-chart :kind="$a['chart']['kind']" :payload="$a['chart']['payload']" />
                        @endif

                        {{-- Contributing signals — the causal path, not a summary. --}}
                        @if ($a['factors'])
                            <div class="stack-2">
                                <div class="eyebrow">Contributing signals</div>
                                @foreach ($a['factors'] as $f)
                                    <a class="factor" href="{{ $f['route'] }}" wire:navigate>
                                        <span class="badge badge--{{ $f['value'] }}"><span>{{ $f['value'] }}</span></span>
                                        <span class="grow">{{ $f['label'] }}</span>
                                        @if (! empty($f['note']))<span class="t-sm t-3">{{ $f['note'] }}</span>@endif
                                        <x-icon name="right" size="14" />
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if ($a['items'])
                            <div class="stack-2">
                                @foreach ($a['items'] as $it)
                                    @if (! empty($it['route']))
                                        <a class="factor" href="{{ $it['route'] }}" wire:navigate>
                                            @if (! empty($it['band']))
                                                <span class="badge badge--{{ $it['band'] }}"><span>{{ $it['band'] }}</span></span>
                                            @else
                                                <x-icon name="check" size="14" />
                                            @endif
                                            <div class="grow">
                                                <div>{{ $it['title'] }}</div>
                                                @if (! empty($it['note']))<div class="t-sm t-3">{{ $it['note'] }}</div>@endif
                                            </div>
                                            @if (! empty($it['value']))
                                                <span class="badge badge--ai"><span>{{ $it['value'] }}</span></span>
                                            @endif
                                            @if (! empty($it['area']))
                                                <span class="t-sm t-3">{{ InsightEngine::AREAS[$it['area']] ?? $it['area'] }}</span>
                                            @endif
                                            <x-icon name="right" size="14" />
                                        </a>
                                    @elseif (! empty($it['ask']))
                                        <button class="factor" wire:click="submit(@js($it['ask']))">
                                            <x-icon name="ask" size="14" />
                                            <div class="grow">{{ $it['title'] }}</div>
                                            <x-icon name="right" size="14" />
                                        </button>
                                    @else
                                        <div class="factor">
                                            <x-icon name="check" size="14" />
                                            <div class="grow">{{ $it['title'] }}</div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @if ($a['table'])
                            <div class="card" style="overflow:hidden">
                                <div class="tablewrap">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                @foreach ($a['table']['columns'] as $i => $col)
                                                    <th class="{{ $i ? 'right' : '' }}">{{ $col }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($a['table']['rows'] as $row)
                                                <tr>
                                                    @foreach ($row as $i => $cell)
                                                        <td class="{{ $i ? 'right num' : '' }}">{{ $cell }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <div class="row-2 wrap" style="margin-top:4px">
                            @if ($a['drill'])
                                <a class="btn btn--sm btn--ghost" href="{{ $a['drill']['route'] }}" wire:navigate>
                                    <span>{{ $a['drill']['label'] }}</span><x-icon name="right" size="13" />
                                </a>
                            @endif
                            <span class="spacer"></span>
                            {{-- Shown deliberately: when the router misreads a question,
                                 the user should be able to see that it did. --}}
                            <span class="t-xs t-4" title="How the question was parsed">
                                intent: {{ $a['parsed']['intent'] }}@if ($a['parsed']['metric']) · metric: {{ $a['parsed']['metric'] }}@endif
                                @if ($a['parsed']['dim']) · by {{ $a['parsed']['dim'] }}@endif
                                · window: {{ $a['parsed']['windowLabel'] }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
