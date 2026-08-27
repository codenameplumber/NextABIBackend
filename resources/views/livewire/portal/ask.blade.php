@php use App\Support\Fmt; @endphp

<div class="stack">
    <x-page-head title="Ask NexABI"
                 sub="Work out what fits your budget, or ask about your own payments" />

    <x-page-note>
        Every figure here is worked out from the live catalogue and your own payment file.
        **When the data cannot answer something, it says so** rather than guessing — and anything
        about the property itself is a question for your realtor.
    </x-page-note>

    {{-- The box first. Somebody arriving here has a question already. --}}
    <form class="askbar" wire:submit="submit">
        <input type="text" wire:model="draft" autocomplete="off"
               placeholder="What can I afford at ₦500,000 a month?"
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
            <div class="eyebrow">Things people ask</div>
            <div class="askchips">
                @foreach ($suggestions as $s)
                    <button class="askchip" wire:click="submit(@js($s))">{{ $s }}</button>
                @endforeach
            </div>
        </div>
    @endif

    @foreach ($answers as $i => $a)
        <article class="answer" wire:key="ans-{{ $i }}">
            <div class="answer__q">
                <x-icon name="ask" size="14" />
                <span>{{ $a['question'] }}</span>
            </div>

            <h2 class="answer__h">{{ $a['headline'] }}</h2>

            <p class="answer__n">{!! Fmt::rich($a['narrative']) !!}</p>

            {{-- Actual units, when the question was an affordability one. The
                 point is to end on something clickable rather than on advice. --}}
            @if (! empty($a['items']))
                <div class="answer__list">
                    @foreach ($a['items'] as $it)
                        @if ($it['ref'])
                            <a class="optrow" href="{{ route('portal.commit', $it['ref']) }}" wire:navigate>
                                <div class="optrow__id">
                                    <span class="optrow__t">{{ $it['title'] }}</span>
                                    <span class="optrow__s">{{ $it['sub'] }}</span>
                                </div>
                                <div class="optrow__v">
                                    <span class="v">{{ $it['value'] }}</span>
                                    <span class="n">{{ $it['note'] }}</span>
                                </div>
                                <x-icon name="right" size="14" />
                            </a>
                        @else
                            <div class="optrow">
                                <div class="optrow__id">
                                    <span class="optrow__t">{{ $it['title'] }}</span>
                                    <span class="optrow__s">{{ $it['sub'] }}</span>
                                </div>
                                <div class="optrow__v">
                                    <span class="v">{{ $it['value'] }}</span>
                                    <span class="n">{{ $it['note'] }}</span>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if (! empty($a['more']) && $a['more'] > 0)
                    <a class="link t-sm" href="{{ route('portal.browse') }}" wire:navigate>
                        {{ $a['more'] }} more also fit — browse them all
                    </a>
                @endif
            @endif

            @if ($a['kind'] === 'none')
                <div class="answer__foot">
                    <span class="t-sm t-3">Your realtor can answer this one.</span>
                    <a class="btn btn--sm btn--ghost" href="{{ route('portal.support') }}" wire:navigate>
                        <span>Message them</span>
                    </a>
                </div>
            @endif
        </article>
    @endforeach
</div>
