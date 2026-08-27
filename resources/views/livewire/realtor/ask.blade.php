@php use App\Support\Fmt; @endphp

<div class="stack">
    <x-page-head title="Ask NexABI"
                 sub="Where buyers are looking, what is moving, and how your own book is doing" />

    <x-page-note>
        Demand is measured across **the whole market**, because that is the signal worth acting on.
        Performance figures are **yours alone** — nothing here shows another realtor's book.
    </x-page-note>

    <form class="askbar" wire:submit="submit">
        <input type="text" wire:model="draft" autocomplete="off"
               placeholder="Which location should I focus my marketing on?"
               aria-label="Ask a question">
        <button class="btn btn--primary" type="submit"
                wire:loading.attr="disabled" wire:target="submit">
            <x-icon name="ask" size="15" />
            <span wire:loading.remove wire:target="submit">Ask</span>
            <span wire:loading wire:target="submit">Thinking…</span>
        </button>
    </form>

    @if (empty($answers))
        <div class="stack-2">
            <div class="eyebrow">Start with one of these</div>
            <div class="askchips">
                @foreach ($suggestions as $s)
                    <button class="askchip" wire:click="submit(@js($s))">{{ $s }}</button>
                @endforeach
            </div>
        </div>
    @endif

    @foreach ($answers as $i => $a)
        <article class="answer" wire:key="rans-{{ $i }}">
            <div class="answer__q">
                <x-icon name="ask" size="14" />
                <span>{{ $a['question'] }}</span>
            </div>

            <h2 class="answer__h">{{ $a['headline'] }}</h2>

            <p class="answer__n">{!! Fmt::rich($a['narrative']) !!}</p>

            @if (! empty($a['rows']))
                <div class="tablewrap">
                    <table class="table">
                        <thead>
                            <tr>
                                @foreach ($a['columns'] as $ci => $col)
                                    <th class="{{ $ci > 0 ? 'right' : '' }}">{{ $col }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($a['rows'] as $row)
                                <tr>
                                    @foreach ($row as $ci => $cell)
                                        <td class="{{ $ci > 0 ? 'right num' : 'cell-primary' }}">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($a['kind'] === 'ranking' && ! empty($a['rows']))
                <div class="answer__foot">
                    <span class="t-sm t-3">Turn this into work:</span>
                    <a class="btn btn--sm btn--ghost" href="{{ route('realtor.properties') }}" wire:navigate>
                        <span>Find stock there</span>
                    </a>
                    <a class="btn btn--sm btn--ghost" href="{{ route('realtor.materials') }}" wire:navigate>
                        <span>Grab materials</span>
                    </a>
                </div>
            @endif
        </article>
    @endforeach
</div>
