@php
    use App\Engine\InsightEngine;
    use App\Support\Fmt;
@endphp

{{--
    The root has to be a single real element.

    Opening with @if makes Livewire emit its <!--[if BLOCK]--> marker first,
    and the framework then reads an empty tag name when it records this
    component as a child — which surfaces later as "Invalid Livewire child tag
    name" on the parent's next render. One wrapper div costs nothing and keeps
    the root unambiguous.
--}}
<div wire:key="card-{{ $insightId }}">
@if ($insight && ! $retired)
    {{--
        Three questions in a fixed order, because that is the order somebody
        actually asks them:

            what is happening   ← the detector's own headline
            what it means       ← the narrated figures
            what to do          ← the primary playbook, as a sentence

        The action is a sentence rather than a button everywhere except the
        Insights screen. A finding sitting above a table is context for the
        table; being handed something to execute mid-scroll is what turned the
        old cards into things people scrolled past.
    --}}
    <section class="finding rise" data-sev="{{ $insight->band }}">

        <header class="finding__head">
            <span class="finding__glyph"><x-icon name="insight" size="15" /></span>

            <span class="finding__area">
                {{ InsightEngine::AREAS[$insight->area] ?? $insight->area }}
            </span>

            <x-badge :kind="$insight->band" dot>{{ $insight->band }}</x-badge>

            @if ($insight->status === 'actioned')
                <x-badge kind="pos" dot>Actioned</x-badge>
            @endif

            <span class="grow"></span>

            @unless ($compact)
                <button class="btn btn--sm btn--icon" wire:click="dismiss"
                        title="Dismiss this finding" aria-label="Dismiss this finding">
                    <x-icon name="x" size="14" />
                </button>
            @endunless
        </header>

        <h3 class="finding__what">{{ $insight->title }}</h3>

        <p class="finding__why">{!! Fmt::rich($narrative) !!}</p>

        @if ($todo)
            <div class="finding__do">
                <span class="finding__do-k">What to do</span>
                <p class="finding__do-v">
                    <b>{{ $todo->label }}</b>@if ($todo->rationale) — {{ $todo->rationale }}@endif
                </p>
            </div>
        @endif

        {{-- Non-negotiable: every figure stays one click from the rows that
             produced it. Removing the buttons does not mean removing the
             audit trail — a number you cannot open is a number nobody
             believes, whatever it is sitting next to. --}}
        @if ($insight->evidence)
            <div class="evidence">
                @foreach ($insight->evidence as $e)
                    @php $drill = $e['drill'] ?? null; @endphp
                    @if ($drill)
                        <a class="evidence__item" data-drill="1" wire:navigate
                           title="Open the underlying records"
                           href="{{ route(InsightEngine::areaRoute($drill['route']), $drill['params'] ?? []) }}">
                            <span class="evidence__k">{{ $e['label'] }}</span>
                            <span class="evidence__v">{{ Fmt::evidence($e) }}</span>
                            <x-icon name="right" size="11" />
                        </a>
                    @else
                        <span class="evidence__item">
                            <span class="evidence__k">{{ $e['label'] }}</span>
                            <span class="evidence__v">{{ Fmt::evidence($e) }}</span>
                        </span>
                    @endif
                @endforeach
            </div>
        @endif

        <footer class="finding__foot">
            {{-- Carries a question written from this finding's own figures, so
                 Ask opens on an answer about this business rather than on an
                 empty box the reader has to phrase something into. --}}
            <a class="btn btn--sm btn--ghost" href="{{ $ask }}" wire:navigate>
                <x-icon name="ask" size="13" />
                <span>Find out more</span>
            </a>

            @if ($showActions && $todo)
                @if ($todo->isDone())
                    <span class="badge badge--pos">
                        <x-icon name="check" size="11" /><span>Applied</span>
                    </span>
                @else
                    <button class="btn btn--sm btn--primary"
                            wire:click="apply({{ $todo->id }})"
                            wire:loading.attr="disabled"
                            wire:target="apply({{ $todo->id }})">
                        <x-icon name="bolt" size="13" />
                        <span wire:loading.remove wire:target="apply({{ $todo->id }})">Apply this</span>
                        <span wire:loading wire:target="apply({{ $todo->id }})">Applying…</span>
                    </button>
                @endif

                @if ($todo->impact > 0)
                    <span class="badge badge--ai" title="Estimated impact">
                        <span>{{ Fmt::moneyShort($todo->impact) }}</span>
                    </span>
                @endif
            @endif

            <span class="grow"></span>

            <span class="t-xs t-4">
                {{ Fmt::pct($insight->confidence, 0) }} confidence ·
                {{ Fmt::titleCase($insight->detector) }}
            </span>
        </footer>

        @if ($showActions && $lastResult)
            <div class="finding__result num">{{ $lastResult }}</div>
        @endif
    </section>
@else
    {{-- The card removes itself once the condition it described stops holding.
         Leaving a resolved finding on screen is how BI tools get distrusted. --}}
@endif
</div>
