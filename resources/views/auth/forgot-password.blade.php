<x-layouts.marketing title="Reset your password — NexABI"
                     description="Send yourself a link to set a new NexABI password.">

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
        </div>
    </div>

    <aside class="auth-aside grain">
        <div>
            <span class="kicker">Why the wording is careful</span>
            <h2 class="display--sm mt-16" style="max-width:17ch">
                We answer the same way either way.
            </h2>
            <p class="lede mt-16" style="font-size:var(--t-md);max-width:44ch">
                Whether or not that address has an account, this page says the same thing.
                Telling you "no such user" would turn a reset form into a way to find out
                who banks with which agency — and this product holds whole client books.
            </p>
            <div class="ed-figures mt-32" style="max-width:520px">
                <div><div class="n">60</div><div class="k">Minutes a link lives</div></div>
                <div><div class="n">1</div><div class="k">Use per link</div></div>
                <div><div class="n">0</div><div class="k">Accounts disclosed</div></div>
            </div>
        </div>
    </aside>
</div>

<script type="module" src="{{ asset('js/site.js') }}"></script>
</x-layouts.marketing>
