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
    <section class="insight rise" data-sev="{{ $insight->band }}">

        <header class="insight__head">
            <div class="insight__glyph"><x-icon name="insight" size="16" /></div>

            <div class="grow">
                <div class="insight__title">{{ $insight->title }}</div>
                <div class="insight__meta">
                    <x-badge :kind="$insight->band" dot>{{ $insight->band }}</x-badge>
                    <x-badge>{{ InsightEngine::AREAS[$insight->area] ?? $insight->area }}</x-badge>
                    <span class="t-xs t-3">{{ Fmt::titleCase($insight->detector) }} detector</span>
                    <span class="t-xs t-4">·</span>
                    <span class="t-xs t-3">{{ Fmt::pct($insight->confidence, 0) }} confidence</span>
                    @if ($insight->status === 'actioned')
                        <x-badge kind="pos" dot>Actioned</x-badge>
                    @endif
                </div>
            </div>

            @unless ($compact)
                <button class="btn btn--sm btn--icon" wire:click="dismiss"
                        title="Dismiss this finding" aria-label="Dismiss this finding">
                    <x-icon name="x" size="15" />
                </button>
            @endunless
        </header>

        <div class="insight__body">
            <p class="insight__narrative">{!! Fmt::rich($narrative) !!}</p>

            {{-- Non-negotiable: every figure is one click from the rows that
                 produced it. This is the mechanism that turns a plausible demo
                 into a credible product. --}}
            @if ($insight->evidence)
                <div class="evidence">
                    @foreach ($insight->evidence as $e)
                        @php $drill = $e['drill'] ?? null; @endphp
                        @if ($drill)
                            <a class="evidence__item" data-drill="1" wire:navigate
                               title="Open the underlying records"
                               href="{{ route('app.'.$drill['route'], $drill['params'] ?? []) }}">
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
        </div>

        @if (! $compact && $insight->recommendations->isNotEmpty())
            <div class="recs">
                @foreach ($insight->recommendations as $r)
                    <div class="rec {{ $r->isDone() ? 'is-done' : '' }}" wire:key="rec-{{ $r->id }}">
                        <div class="rec__text grow">
                            <div class="rec__label">{{ $r->label }}</div>
                            <div class="rec__why">{{ $r->rationale }}</div>
                            @if ($r->isDone() && $lastResult)
                                <div class="t-xs t-3 num" style="margin-top:6px">{{ $lastResult }}</div>
                            @endif
                        </div>

                        <div class="rec__actions">
                            @if ($r->impact > 0)
                                <span class="badge badge--ai" title="Estimated impact">
                                    <span>{{ Fmt::moneyShort($r->impact) }}</span>
                                </span>
                            @endif
                            <span class="badge" title="Effort"><span>{{ $r->effortLabel() }}</span></span>

                            @if ($r->isDone())
                                <span class="badge badge--pos">
                                    <x-icon name="check" size="11" /><span>Applied</span>
                                </span>
                            @else
                                <button class="btn btn--sm {{ $r->is_primary ? 'btn--primary' : 'btn--ghost' }}"
                                        wire:click="apply({{ $r->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="apply({{ $r->id }})">
                                    <x-icon name="bolt" size="13" />
                                    <span wire:loading.remove wire:target="apply({{ $r->id }})">Apply</span>
                                    <span wire:loading wire:target="apply({{ $r->id }})">Applying…</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@else
    {{-- The card removes itself once the condition it described stops holding.
         Leaving a resolved finding on screen is how BI tools get distrusted. --}}
@endif
</div>
