<x-layouts.marketing title="Choose a new password — NexABI">

<div class="auth-shell auth-shell--single">
    <div class="auth-form">

        <a class="brand" href="{{ route('home') }}" style="margin-bottom:var(--s-7)">
            <x-logo variant="mark" :height="30" />
            <span class="brand-text">
                <span class="brand-name">NexABI</span>
                <span class="brand-tag">Adaptive Business Intelligence</span>
            </span>
        </a>

        <h1 class="display--sm">Choose a new password</h1>
        <p class="lede mt-8" style="font-size:var(--t-md)">
            At least 8 characters. Longer beats complicated.
        </p>

        <form method="POST" action="{{ route('password.update') }}" novalidate id="resetForm">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="field mt-20 {{ $errors->has('email') ? 'is-invalid' : '' }}">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" autocomplete="email"
                       value="{{ old('email', $email) }}" required>
                @error('email')<span class="field__err">{{ $message }}</span>@enderror
            </div>

            <div class="field {{ $errors->has('password') ? 'is-invalid' : '' }}">
                <label for="password">New password</label>
                <input type="password" id="password" name="password"
                       autocomplete="new-password" required autofocus>
                <span class="field__hint" id="strength">Use 8 characters or more.</span>
                @error('password')<span class="field__err">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm it</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       autocomplete="new-password" required>
            </div>

            <button class="btn btn--primary btn--lg btn--full mt-24" type="submit">
                <span>Set the password</span>
                <span data-icon="arrow" data-size="16"></span>
            </button>

            <div class="auth-alt"><a href="{{ route('login') }}">Back to sign in</a></div>
        </form>

        {{-- Kept from the panel this page used to carry: it warns about a
             side effect people should know is coming, not after it happens. --}}
        <p class="t-sm t-3 mt-24" style="line-height:1.6">
            Setting a new password rotates the remember-me token, so anything still signed
            in elsewhere is signed out. That is the point of resetting.
        </p>
    </div>
</div>

<script type="module" src="{{ asset('js/site.js') }}"></script>
<script type="module" src="{{ asset('js/password-strength.js') }}"></script>
<script type="module" src="{{ asset('js/password-eye.js') }}"></script>
</x-layouts.marketing>
