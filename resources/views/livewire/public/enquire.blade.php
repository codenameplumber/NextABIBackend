@php use App\Support\Fmt; @endphp

<div class="sec-wrap" style="max-width:820px">
    @if ($sent)
        {{-- Confirmation, with what happens next. "Thanks!" and nothing else
             leaves someone wondering whether it went anywhere. --}}
        <div class="hold">
            <div class="hold__ico"><x-icon name="check" size="26" /></div>
            <h1>Got it — thank you.</h1>
            <p>
                @if ($realtor)
                    <b>{{ $realtor->name }}</b> has your enquiry and will call you on the number
                    you gave, usually within a working day.
                @else
                    One of our realtors will call you on the number you gave, usually within a
                    working day.
                @endif
            </p>
            <div class="row-2" style="justify-content:center;margin-top:var(--s-5)">
                <a class="btn btn--primary" href="{{ route('properties') }}" wire:navigate>
                    <span>Look at what is available</span>
                </a>
            </div>
        </div>
    @else
        <div class="sec-h">
            <div>
                <h2>Tell us what you are looking for</h2>
                <p>Two minutes. No account needed — we will call you.</p>
            </div>
        </div>

        @if ($realtor)
            <div class="ref-note">
                <span class="agent-avatar" style="width:30px;height:30px;font-size:11px">
                    {{ $realtor->initials() }}
                </span>
                <span>
                    <b>{{ $realtor->name }}</b> sent you here — your enquiry goes straight to them.
                </span>
            </div>
        @endif

        <form wire:submit="submit" class="step-c" style="padding:var(--s-6)">
            <div class="fgrid">
                <div class="field fgrid--full">
                    <label for="e-name">Your name</label>
                    <input id="e-name" class="input" wire:model="name" autocomplete="name">
                    @error('name') <span class="err">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="e-phone">Phone number</label>
                    <input id="e-phone" class="input" wire:model="phone" autocomplete="tel"
                           placeholder="080…">
                    @error('phone') <span class="err">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="e-email">Email <span class="hint">optional</span></label>
                    <input id="e-email" class="input" type="email" wire:model="email"
                           autocomplete="email">
                    @error('email') <span class="err">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="e-state">State</label>
                    <select id="e-state" class="input" wire:model.live="state">
                        <option value="">Choose your state</option>
                        @foreach ($states as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                    @error('state') <span class="err">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="e-lga">Local government</label>
                    <select id="e-lga" class="input" wire:model="lga" @disabled(! $state)>
                        <option value="">{{ $state ? 'Choose your LGA' : 'Pick a state first' }}</option>
                        @foreach ($lgas as $l)
                            <option value="{{ $l }}">{{ $l }}</option>
                        @endforeach
                    </select>
                    @error('lga') <span class="err">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="e-occ">What do you do?</label>
                    <select id="e-occ" class="input" wire:model="occupation">
                        <option value="">Choose one</option>
                        @foreach ($occupations as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('occupation') <span class="err">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="e-inc">Annual income</label>
                    <select id="e-inc" class="input" wire:model="income_bracket">
                        <option value="">Choose a range</option>
                        @foreach ($incomes as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="hint">Helps us show you what actually fits.</span>
                    @error('income_bracket') <span class="err">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="e-where">Where do you want to buy? <span class="hint">optional</span></label>
                    <select id="e-where" class="input" wire:model="wants_location">
                        <option value="">No preference</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc }}">{{ $loc }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="e-type">What kind? <span class="hint">optional</span></label>
                    <select id="e-type" class="input" wire:model="wants_type">
                        <option value="">No preference</option>
                        @foreach ($types as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="e-budget">Budget <span class="hint">optional</span></label>
                    <input id="e-budget" class="input" type="number" min="0" step="1000000"
                           wire:model="budget" placeholder="e.g. 45000000">
                    @error('budget') <span class="err">{{ $message }}</span> @enderror
                </div>

                <div class="field fgrid--full">
                    <label for="e-heard">How did you hear about us?</label>
                    <select id="e-heard" class="input" wire:model="heard_about_us">
                        <option value="">Choose one</option>
                        @foreach ($sources as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('heard_about_us') <span class="err">{{ $message }}</span> @enderror
                </div>
            </div>

            <div style="margin-top:var(--s-6);display:flex;gap:var(--s-3);align-items:center;flex-wrap:wrap">
                <button class="btn btn--primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submit">Send my enquiry</span>
                    <span wire:loading wire:target="submit">Sending…</span>
                </button>
                <span class="t-sm t-3">We will never share your details.</span>
            </div>
        </form>
    @endif
</div>
