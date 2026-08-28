@php use App\Support\Fmt; @endphp

<div class="commit" style="padding:0">
    <div>
        <a class="btn btn--ghost btn--sm" href="{{ route('portal.browse') }}" wire:navigate
           style="margin-bottom:var(--s-4)">
            <x-icon name="left" size="13" /><span>Back to browsing</span>
        </a>

        <div class="pcard__art" style="border-radius:var(--r-lg);border:1px solid var(--border);overflow:hidden">
            <x-property-art :property="$property" />
        </div>

        <h1 style="font-family:var(--font-display);font-size:var(--t-2xl);letter-spacing:-.02em;margin:var(--s-5) 0 var(--s-2)">
            {{ $property->title }}
        </h1>

        <p class="t-lg t-2" style="margin:0 0 var(--s-5)">
            {{ $property->location }} · {{ $property->bedrooms }} bed {{ $property->type }}
            @if ($property->size_sqm) · {{ Fmt::num($property->size_sqm) }} m² @endif
        </p>

        @if ($property->description)
            <p style="max-width:60ch;line-height:1.65;color:var(--text-2)">{{ $property->description }}</p>
        @endif

        {{-- What actually happens after they press the button. Spelling it out
             here is the difference between a considered commitment and one
             somebody tries to reverse the next morning. --}}
        <div class="hold__steps" style="margin-top:var(--s-6)">
            <div class="semi" style="margin-bottom:var(--s-4)">What happens next</div>

            <div class="row-2" style="align-items:flex-start;gap:var(--s-3);padding-bottom:var(--s-4)">
                <span class="agent-avatar" style="width:26px;height:26px;font-size:11px;flex:none">1</span>
                <div>
                    <div class="semi">The unit is held for you</div>
                    <div class="t-sm t-3">Nobody else can commit to it while we review.</div>
                </div>
            </div>

            <div class="row-2" style="align-items:flex-start;gap:var(--s-3);padding:var(--s-4) 0;border-top:1px solid var(--border)">
                <span class="agent-avatar" style="width:26px;height:26px;font-size:11px;flex:none">2</span>
                <div>
                    <div class="semi">We confirm the details</div>
                    <div class="t-sm t-3">
                        Usually within a working day. You are not committed to anything until
                        this is approved.
                    </div>
                </div>
            </div>

            <div class="row-2" style="align-items:flex-start;gap:var(--s-3);padding-top:var(--s-4);border-top:1px solid var(--border)">
                <span class="agent-avatar" style="width:26px;height:26px;font-size:11px;flex:none">3</span>
                <div>
                    <div class="semi">Your schedule opens</div>
                    <div class="t-sm t-3">
                        Every instalment appears in your payments table with its due date, and
                        fills in as each one is received.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <aside class="terms">
        <div class="terms__cap">Full price</div>
        <div class="terms__fig">{{ Fmt::money($property->price) }}</div>

        <div style="margin:var(--s-5) 0 var(--s-3)">
            <div class="terms__cap" style="margin-bottom:var(--s-3)">
                How long do you want to pay over?
            </div>
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

        {{-- The number the whole decision turns on, updating live. --}}
        <div style="padding:var(--s-4);background:var(--surface-2);border-radius:var(--r-md);margin:var(--s-4) 0">
            <div class="terms__cap">You would pay</div>
            <div class="terms__fig" style="font-size:var(--t-2xl)">{{ Fmt::money($perMonth) }}</div>
            <div class="t-sm t-3" style="margin-top:4px">
                a month, for {{ $months }} months
            </div>
        </div>

        <div class="terms__row"><span>Instalments</span><span>{{ $months }}</span></div>
        <div class="terms__row"><span>Total payable</span><span>{{ Fmt::money($property->price) }}</span></div>
        <div class="terms__row"><span>First payment</span><span>{{ Fmt::dateShort(now()->addMonth()) }}</span></div>

        @error('months') <div class="err" style="margin-top:var(--s-3)">{{ $message }}</div> @enderror

        <label class="row-2" style="align-items:flex-start;gap:var(--s-2);margin:var(--s-5) 0 var(--s-3);cursor:pointer">
            <input type="checkbox" wire:model.live="agreed" style="margin-top:3px">
            <span class="t-sm t-2">
                I have read the terms and understand the schedule opens once this is approved.
            </span>
        </label>
        @error('agreed') <div class="err">{{ $message }}</div> @enderror

        <button class="btn btn--primary" style="width:100%;justify-content:center"
                wire:click="submitCommitment" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="submitCommitment">Commit to this property</span>
            <span wire:loading wire:target="submitCommitment">Submitting…</span>
        </button>
    </aside>
</div>
