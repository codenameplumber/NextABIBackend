@php use App\Support\Fmt; @endphp

<div class="commit">
    <div>
        <a class="btn btn--ghost btn--sm" href="{{ route('properties') }}" wire:navigate
           style="margin-bottom:var(--s-4)">
            <x-icon name="left" size="13" /><span>All properties</span>
        </a>

        <div class="pcard__art" style="border-radius:var(--r-lg);border:1px solid var(--border);overflow:hidden">
            <x-property-art :property="$property" />
        </div>

        <h1 style="font-family:var(--font-display);font-size:var(--t-3xl);letter-spacing:-.02em;margin:var(--s-5) 0 var(--s-2)">
            {{ $property->title }}
        </h1>

        <p class="t-lg t-2" style="margin:0 0 var(--s-5)">
            {{ $property->location }} · {{ $property->bedrooms }} bed {{ $property->type }}
            @if ($property->size_sqm) · {{ Fmt::num($property->size_sqm) }} m² @endif
        </p>

        @if ($property->description)
            <p style="max-width:62ch;line-height:1.65;color:var(--text-2)">{{ $property->description }}</p>
        @endif

        <div class="fgrid" style="margin-top:var(--s-6)">
            <div class="step-c">
                <span class="step-c__n">REFERENCE</span>
                <h3 style="font-family:var(--mono);font-size:var(--t-lg)">{{ $property->ref }}</h3>
            </div>
            <div class="step-c">
                <span class="step-c__n">ON THE MARKET</span>
                <h3>{{ Fmt::ago($property->listed_at) }}</h3>
            </div>
            <div class="step-c">
                <span class="step-c__n">ENQUIRIES</span>
                <h3>{{ Fmt::num($property->inquiries) }}</h3>
            </div>
        </div>

        @if ($similar->isNotEmpty())
            <div class="sec-h" style="margin-top:var(--s-8)">
                <div><h2>Also in {{ $property->location }}</h2></div>
            </div>
            <div class="pgrid">
                @foreach ($similar as $s)
                    <a class="pcard" href="{{ route('property', $s->ref) }}" wire:navigate>
                        <div class="pcard__art"><x-property-art :property="$s" /></div>
                        <div class="pcard__b">
                            <h3 class="pcard__t">{{ $s->title }}</h3>
                            <span class="pcard__loc">{{ $s->bedrooms }} bed {{ $s->type }}</span>
                            <span class="pcard__price">{{ Fmt::money($s->price) }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ─────────────────────────────────────────────────── the terms ──
         The monthly figure updates as the term moves. That number is the
         whole decision, so it is never behind a submit button.        --}}

    <aside class="terms">
        <div class="terms__cap">Full price</div>
        <div class="terms__fig">{{ Fmt::money($property->price) }}</div>

        <div style="margin:var(--s-5) 0 var(--s-3)">
            <div class="terms__cap" style="margin-bottom:var(--s-3)">Pay over</div>
            <div class="durations">
                @foreach ($options as $m)
                    <button type="button"
                            class="dur {{ $months === $m ? 'is-on' : '' }}"
                            wire:click="$set('months', {{ $m }})">
                        {{ $m }}m
                    </button>
                @endforeach
            </div>
        </div>

        <div style="padding:var(--s-4);background:var(--surface-2);border-radius:var(--r-md);margin:var(--s-4) 0">
            <div class="terms__cap">Your monthly instalment</div>
            <div class="terms__fig" style="font-size:var(--t-2xl)">{{ Fmt::money($perMonth) }}</div>
            <div class="t-sm t-3" style="margin-top:4px">
                {{ $months }} payments · first one a month after approval
            </div>
        </div>

        <div class="terms__row"><span>Term</span><span>{{ $months }} months</span></div>
        <div class="terms__row"><span>Total payable</span><span>{{ Fmt::money($property->price) }}</span></div>
        <div class="terms__row"><span>Reference</span><span class="mono">{{ $property->ref }}</span></div>

        <div style="margin-top:var(--s-5);display:flex;flex-direction:column;gap:var(--s-2)">
            @auth
                @if (auth()->user()->isInvestor())
                    <a class="btn btn--primary" style="justify-content:center"
                       href="{{ route('portal.commit', $property->ref) }}" wire:navigate>
                        <span>Commit to this property</span>
                    </a>
                @else
                    <a class="btn btn--ghost" style="justify-content:center"
                       href="{{ route('enquire') }}" wire:navigate>
                        <span>Make an enquiry</span>
                    </a>
                @endif
            @else
                <a class="btn btn--primary" style="justify-content:center"
                   href="{{ route('register') }}">
                    <span>Register to commit</span>
                </a>
                <a class="btn btn--ghost" style="justify-content:center"
                   href="{{ route('enquire') }}" wire:navigate>
                    <span>Ask a question first</span>
                </a>
            @endauth
        </div>

        <p class="t-xs t-4" style="margin:var(--s-4) 0 0;line-height:1.5">
            Committing holds the unit while we confirm the details. Nothing is binding until
            it is approved and you have seen the full schedule.
        </p>
    </aside>
</div>
