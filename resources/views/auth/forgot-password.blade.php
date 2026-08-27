<x-layouts.marketing title="Reset your password — NexABI"
                     description="Send yourself a link to set a new NexABI password.">

<div class="auth-shell auth-shell--single">
    <div class="auth-form">

        <a class="brand" href="{{ route('home') }}" style="margin-bottom:var(--s-7)">
            <x-logo variant="mark" :height="30" />
            <span class="brand-text">
                <span class="brand-name">NexABI</span>
                <span class="brand-tag">Adaptive Business Intelligence</span>
            </span>
        </a>

        <h1 class="display--sm">Reset your password</h1>
        <p class="lede mt-8" style="font-size:var(--t-md)">
            Give us the address on the account and we will send a link.
        </p>

        @if (session('status'))
            <div class="demo-note mt-24">
                <span data-icon="check" data-size="15"></span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf

            <div class="field mt-20 {{ $errors->has('email') ? 'is-invalid' : '' }}">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" autocomplete="email"
                       value="{{ old('email') }}" placeholder="you@agency.com" required autofocus>
                @error('email')<span class="field__err">{{ $message }}</span>@enderror
            </div>

            <button class="btn btn--primary btn--lg btn--full mt-24" type="submit">
                <span>Send the link</span>
                <span data-icon="arrow" data-size="16"></span>
            </button>

            <div class="auth-alt">
                Remembered it? <a href="{{ route('login') }}">Sign in</a>
                &nbsp;·&nbsp;
                <a href="{{ route('home') }}">Back to the site</a>
            </div>
        </form>

        {{-- Kept from the panel this page used to carry. It explains a
             behaviour people otherwise read as a bug: submitting a valid
             address and an unknown one look identical, deliberately. --}}
        <p class="t-sm t-3 mt-24" style="line-height:1.6">
            Whether or not that address has an account, this page says the same thing.
            Telling you "no such user" would turn a reset form into a way to find out who
            banks with which agency. Links last 60 minutes and work once.
        </p>
    </div>
</div>

<script type="module" src="{{ asset('js/site.js') }}"></script>
</x-layouts.marketing>
