<x-layouts.marketing title="Create your account — NexABI">

<div class="sec-wrap" style="max-width:840px">
    <div class="sec-h">
        <div>
            <h2>Create your account</h2>
            <p>So you can commit to a property and follow every payment.</p>
        </div>
        <a class="t-sm" href="{{ route('login') }}">Already have one? Sign in</a>
    </div>

    {{-- Attribution shown back rather than hidden. Reassurance when it is
         right, and a correctable mistake when it is not. --}}
    @if ($realtor)
        <div class="ref-note">
            <span class="agent-avatar" style="width:30px;height:30px;font-size:11px">
                {{ $realtor->initials() }}
            </span>
            <span><b>{{ $realtor->name }}</b> is registering you — they will be your realtor.</span>
        </div>
    @elseif ($ref)
        <div class="ref-note" style="border-color:var(--sev-high);color:var(--sev-high)">
            <x-icon name="warn" size="16" />
            <span>
                We do not recognise the code <b class="mono">{{ $ref }}</b>. You can still register —
                we will assign you a realtor.
            </span>
        </div>
    @endif

    @if ($errors->any())
        <div class="ref-note" style="border-color:var(--neg);color:var(--neg)">
            <x-icon name="warn" size="16" />
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('register.attempt') }}" class="step-c"
          style="padding:var(--s-6)" x-data="lgaPicker()">
        @csrf
        <input type="hidden" name="ref" value="{{ $ref }}">

        <div class="fgrid">
            <div class="field">
                <label for="r-name">Full name</label>
                <input id="r-name" class="input" name="name" value="{{ old('name') }}"
                       autocomplete="name" required>
            </div>

            <div class="field">
                <label for="r-phone">Phone number</label>
                <input id="r-phone" class="input" name="phone" value="{{ old('phone') }}"
                       autocomplete="tel" placeholder="080…">
            </div>

            <div class="field fgrid--full">
                <label for="r-email">Email address</label>
                <input id="r-email" class="input" type="email" name="email"
                       value="{{ old('email') }}" autocomplete="email" required>
            </div>

            <div class="field">
                <label for="r-state">State</label>
                <select id="r-state" class="input" name="state" x-model="state"
                        @change="lga = ''" required>
                    <option value="">Choose your state</option>
                    @foreach ($states as $s)
                        <option value="{{ $s }}" @selected(old('state') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="r-lga">Local government</label>
                <select id="r-lga" class="input" name="lga" x-model="lga"
                        :disabled="!state" required>
                    <option value="">Pick a state first</option>
                    <template x-for="l in (map[state] || [])" :key="l">
                        <option :value="l" x-text="l"></option>
                    </template>
                </select>
            </div>

            <div class="field">
                <label for="r-occ">What do you do?</label>
                <select id="r-occ" class="input" name="occupation" required>
                    <option value="">Choose one</option>
                    @foreach ($occupations as $k => $label)
                        <option value="{{ $k }}" @selected(old('occupation') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="r-inc">Annual income</label>
                <select id="r-inc" class="input" name="income_bracket" required>
                    <option value="">Choose a range</option>
                    @foreach ($incomes as $k => $label)
                        <option value="{{ $k }}" @selected(old('income_bracket') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="hint">Helps us show you what actually fits.</span>
            </div>

            <div class="field fgrid--full">
                <label for="r-heard">How did you hear about us?</label>
                <select id="r-heard" class="input" name="heard_about_us" required>
                    <option value="">Choose one</option>
                    @foreach ($sources as $k => $label)
                        <option value="{{ $k }}"
                            @selected(old('heard_about_us') === $k || (! old('heard_about_us') && $realtor && $k === 'realtor'))>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="r-pass">Password</label>
                <input id="r-pass" class="input" type="password" name="password"
                       autocomplete="new-password" required minlength="8">
                <span class="hint">At least 8 characters.</span>
            </div>

            <div class="field">
                <label for="r-pass2">Confirm password</label>
                <input id="r-pass2" class="input" type="password" name="password_confirmation"
                       autocomplete="new-password" required minlength="8">
            </div>
        </div>

        <div style="margin-top:var(--s-6);display:flex;gap:var(--s-4);align-items:center;flex-wrap:wrap">
            <button class="btn btn--primary" type="submit"><span>Create my account</span></button>
            <span class="t-sm t-3">
                Looking to sell instead?
                <a href="{{ route('realtor.apply') }}">Apply as a realtor</a>.
            </span>
        </div>
    </form>
</div>

{{-- The state-to-LGA map, inlined. Alpine arrives with @livewireScripts in the
     layout. 774 entries is small enough to ship with the page, and avoids a
     round trip every time somebody changes their state. --}}
<script>
    function lgaPicker() {
        return {
            map: @json($lgaMap),
            state: @json(old('state', '')),
            lga: @json(old('lga', '')),
        };
    }
</script>

<script type="module" src="{{ asset('js/password-eye.js') }}"></script>
</x-layouts.marketing>
