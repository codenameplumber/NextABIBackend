<x-layouts.marketing title="Become a realtor — NexABI">

<div class="sec-wrap" style="max-width:760px">
    <div class="sec-h">
        <div>
            <h2>Become a realtor</h2>
            <p>Bring buyers, earn commission on every sale you close.</p>
        </div>
        <a class="t-sm" href="{{ route('login') }}">Already applied? Sign in</a>
    </div>

    {{-- What they get, before the form. Nobody fills in six fields to find out
         what they are signing up for. --}}
    <div class="fgrid" style="margin-bottom:var(--s-6)">
        <div class="step-c">
            <span class="step-c__n">YOUR LINK</span>
            <h3>A registration link of your own</h3>
            <p>Send it to a client and they arrive already attributed to you.</p>
        </div>
        <div class="step-c">
            <span class="step-c__n">MATERIALS</span>
            <h3>Ready to send</h3>
            <p>Flyers and messages with your name, number and link already in them.</p>
        </div>
        <div class="step-c">
            <span class="step-c__n">COMMISSION</span>
            <h3>Up to 15% per sale</h3>
            <p>Set per property and shown against every line of your statement.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="ref-note" style="border-color:var(--neg);color:var(--neg)">
            <x-icon name="warn" size="16" />
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('realtor.apply.attempt') }}" class="step-c"
          style="padding:var(--s-6)">
        @csrf

        <div class="fgrid">
            <div class="field">
                <label for="a-name">Full name</label>
                <input id="a-name" class="input" name="name" value="{{ old('name') }}"
                       autocomplete="name" required>
            </div>

            <div class="field">
                <label for="a-phone">Phone number</label>
                <input id="a-phone" class="input" name="phone" value="{{ old('phone') }}"
                       autocomplete="tel" placeholder="080…" required>
                <span class="hint">Clients will be given this.</span>
            </div>

            <div class="field fgrid--full">
                <label for="a-email">Email address</label>
                <input id="a-email" class="input" type="email" name="email"
                       value="{{ old('email') }}" autocomplete="email" required>
            </div>

            <div class="field">
                <label for="a-pass">Password</label>
                <input id="a-pass" class="input" type="password" name="password"
                       autocomplete="new-password" required minlength="8">
                <span class="hint">At least 8 characters.</span>
            </div>

            <div class="field">
                <label for="a-pass2">Confirm password</label>
                <input id="a-pass2" class="input" type="password" name="password_confirmation"
                       autocomplete="new-password" required minlength="8">
            </div>
        </div>

        <div style="margin-top:var(--s-6);display:flex;gap:var(--s-4);align-items:center;flex-wrap:wrap">
            <button class="btn btn--primary" type="submit"><span>Submit my application</span></button>
            <span class="t-sm t-3">
                We review every application. You will get your code once you are approved.
            </span>
        </div>
    </form>
</div>

<script type="module" src="{{ asset('js/password-eye.js') }}"></script>
</x-layouts.marketing>
