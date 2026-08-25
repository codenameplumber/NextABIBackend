<x-layouts.marketing title="Choose a new password — NexABI">

<div class="auth-shell">
    <div class="auth-form-side">
        <div class="auth-form">

            <a class="brand" href="{{ route('home') }}" style="margin-bottom:var(--s-7)">
                <span class="brand-mark">
                    <svg viewBox="0 0 100 100" width="19" height="19" aria-hidden="true">
                        <path d="M28 72V28h11l22 30V28h11v44H61L39 42v30z" fill="currentColor"/>
                    </svg>
                </span>
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
        </div>
    </div>

    <aside class="auth-aside grain">
        <div>
            <span class="kicker">After this</span>
            <h2 class="display--sm mt-16" style="max-width:17ch">
                Every other session is signed out.
            </h2>
            <p class="lede mt-16" style="font-size:var(--t-md);max-width:44ch">
                Changing the password rotates the remember-me token, so anything still
                signed in elsewhere stops being signed in. That is the point of resetting.
            </p>
        </div>
    </aside>
</div>

<script type="module" src="{{ asset('js/site.js') }}"></script>
<script type="module" src="{{ asset('js/password-strength.js') }}"></script>
</x-layouts.marketing>
