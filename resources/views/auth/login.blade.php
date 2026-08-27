<x-layouts.marketing title="Sign in — NexABI"
                     description="Sign in to the NexABI agency console, the realtor area or the investor portal.">

{{-- One centred card. The panel that used to sit alongside it carried a
     product screenshot, and a screenshot of the thing you are one click away
     from seeing live is an advertisement for the page you are already on. --}}
<div class="auth-shell auth-shell--single">
    <div class="auth-form">

        <a class="brand" href="{{ route('home') }}" style="margin-bottom:var(--s-7)">
            <x-logo variant="mark" :height="30" />
            <span class="brand-text">
                <span class="brand-name">NexABI</span>
                <span class="brand-tag">Adaptive Business Intelligence</span>
            </span>
        </a>

        <h1 class="display--sm">Welcome back</h1>
        <p class="lede mt-8" style="font-size:var(--t-md)">
            Sign in to your account.
        </p>

        @if (session('status'))
            <div class="demo-note mt-24">
                <span data-icon="check" data-size="15"></span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" novalidate id="loginForm">
            @csrf

            <div class="field {{ $errors->has('email') ? 'is-invalid' : '' }}" id="fEmail">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" autocomplete="email"
                       placeholder="you@agency.com" value="{{ old('email') }}" required autofocus>
                @error('email')<span class="field__err">{{ $message }}</span>@enderror
            </div>

            <div class="field {{ $errors->has('password') ? 'is-invalid' : '' }}" id="fPass">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       autocomplete="current-password" placeholder="••••••••" required>
                @error('password')<span class="field__err">{{ $message }}</span>@enderror
            </div>

            {{-- The seat picker used to sit here, asking which lens an admin
                 wanted the insight feed ranked through. It is gone: it is a
                 preference, not a credential, and asking for it at the door
                 made signing in look like a configuration step. The account
                 already carries a role, so the feed is ranked correctly
                 without being asked — and it is changeable inside the console
                 by whoever actually wants to change it. --}}

            <label class="row-2 t-sm t-2" style="margin-top:var(--s-3)">
                <input type="checkbox" name="remember" value="1">
                <span>Keep me signed in</span>
            </label>

            <button class="btn btn--primary btn--lg btn--full mt-24" type="submit">
                <span>Sign in</span>
                <span data-icon="arrow" data-size="16"></span>
            </button>

            <div class="auth-alt">
                New here? <a href="{{ route('register') }}">Create an account</a>
                &nbsp;·&nbsp;
                <a href="{{ route('password.request') }}">Forgot your password?</a>
                &nbsp;·&nbsp;
                <a href="{{ route('home') }}">Back to the site</a>
            </div>
        </form>
    </div>
</div>

<script type="module" src="{{ asset('js/site.js') }}"></script>
<script type="module" src="{{ asset('js/password-eye.js') }}"></script>
</x-layouts.marketing>
