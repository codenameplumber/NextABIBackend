<x-layouts.marketing title="Sign in — NexABI"
                     description="Sign in to the NexABI agency console or the client portal.">

<div class="auth-shell">

    {{-- ── Form side ────────────────────────────────────────────────── --}}
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

            <h1 class="display--sm">Welcome back</h1>
            <p class="lede mt-8" style="font-size:var(--t-md)">
                Choose where you're going and sign in.
            </p>

            @if (session('status'))
                <div class="demo-note mt-24">
                    <span data-icon="check" data-size="15"></span>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            {{-- Real accounts, real sessions, real password hashing. The chips
                 below fill the form; they do not bypass the guard. --}}
            @if ($demoAccounts->isNotEmpty())
                <div class="demo-note mt-24">
                    <span data-icon="info" data-size="15"></span>
                    <span>
                        <b>Reviewing this?</b> Every account below is a real user with a hashed
                        password (<code>password</code>). Pick a seat to fill the form — sessions,
                        roles and tenant isolation all work exactly as they would in production.
                    </span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" novalidate id="loginForm">
                @csrf

                {{-- Where you are going. The console and the portal are different
                     products for different people; asking up front is kinder than
                     signing someone in and then redirecting them. --}}
                <div class="field" style="margin-bottom:var(--s-5)">
                    <label>Where are you going?</label>
                    <div class="role-pick">
                        <label class="role-opt">
                            <input type="radio" name="destination" value="agency"
                                   {{ old('destination', 'agency') === 'agency' ? 'checked' : '' }}>
                            <span class="role-opt__t">
                                <span data-icon="chart" data-size="16"></span>Agency
                            </span>
                            <span class="role-opt__d">The full BI console — insights, leads, finance, reports</span>
                        </label>
                        <label class="role-opt">
                            <input type="radio" name="destination" value="client"
                                   {{ old('destination') === 'client' ? 'checked' : '' }}>
                            <span class="role-opt__t">
                                <span data-icon="home" data-size="16"></span>Client
                            </span>
                            <span class="role-opt__d">My property, payments, documents and support</span>
                        </label>
                    </div>
                </div>

                <div class="field {{ $errors->has('email') ? 'is-invalid' : '' }}" id="fEmail">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" autocomplete="email"
                           placeholder="you@agency.com" value="{{ old('email') }}" required>
                    @error('email')<span class="field__err">{{ $message }}</span>@enderror
                </div>

                <div class="field {{ $errors->has('password') ? 'is-invalid' : '' }}" id="fPass">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           autocomplete="current-password" placeholder="••••••••" required>
                    @error('password')<span class="field__err">{{ $message }}</span>@enderror
                </div>

                {{-- Which seat you take inside the console. It re-orders the feed;
                     it never hides anything, and it is meaningless for a buyer —
                     so it hides itself when Client is selected. --}}
                <div class="field" id="fSeat">
                    <label for="seat">Sign in as</label>
                    <select id="seat" name="seat" class="field-select">
                        @foreach (App\Engine\InsightEngine::ROLES as $key => $role)
                            <option value="{{ $key }}" @selected(old('seat') === $key)>{{ $role['label'] }}</option>
                        @endforeach
                    </select>
                    <span class="field__hint">
                        Changes how insights are ranked for you. It re-orders the feed — it
                        never hides anything.
                    </span>
                </div>

                @if ($demoAccounts->isNotEmpty())
                    <div class="field" style="margin-bottom:var(--s-4)">
                        <label>Demo accounts</label>
                        <div class="chips">
                            @foreach ($demoAccounts as $acct)
                                <button type="button" class="chip"
                                        data-fill="{{ $acct->email }}"
                                        data-dest="{{ $acct->is_client ? 'client' : 'agency' }}"
                                        data-seat="{{ $acct->role }}">
                                    {{ $acct->is_client ? 'Buyer' : $acct->roleLabel() }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

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

    {{-- ── Aside ────────────────────────────────────────────────────── --}}
    <aside class="auth-aside grain">
        <div>
            <span class="kicker">What's waiting inside</span>

            <h2 class="display--sm mt-16" style="max-width:16ch">
                A business that already has something to tell you.
            </h2>

            <p class="lede mt-16" style="font-size:var(--t-md);max-width:44ch">
                The console opens on a seeded Lagos agency — ~1,400 leads, 224 properties,
                20 months of history — with real findings already computed from it.
            </p>

            <div class="shot mt-32" style="max-width:520px">
                <span class="shot-tag"><span class="pulse"></span>Live</span>
                <img src="{{ asset('img/shot-insights.png') }}"
                     alt="The AI Insights feed with ranked findings and executable recommendations"
                     width="1568" height="897" loading="lazy">
                <div class="shot-cap">
                    <span data-icon="insight" data-size="14" style="color:var(--brand-500)"></span>
                    Ranked findings, each with a button that changes the data
                </div>
            </div>

            <div class="ed-figures mt-32" style="max-width:520px">
                <div><div class="n">7</div><div class="k">Detector types</div></div>
                <div><div class="n">14</div><div class="k">Detectors running</div></div>
                <div><div class="n">0</div><div class="k">Hardcoded insights</div></div>
            </div>
        </div>
    </aside>
</div>

<script type="module" src="{{ asset('js/site.js') }}"></script>
<script>
    (function () {
        const form = document.getElementById('loginForm');
        const seatField = document.getElementById('fSeat');
        const email = document.getElementById('email');

        /* The seat picker only makes sense for the agency console — a buyer does
           not have a "role" in a BI tool, so hide it rather than showing a dead
           control. */
        function syncDestination() {
            const dest = form.querySelector('input[name="destination"]:checked').value;
            seatField.style.display = dest === 'agency' ? '' : 'none';
            email.placeholder = dest === 'agency' ? 'you@agency.com' : 'you@example.com';
        }

        form.querySelectorAll('input[name="destination"]')
            .forEach((r) => r.addEventListener('change', syncDestination));
        syncDestination();

        /* Fills the form. It does not sign anyone in — the POST still goes
           through Laravel's guard, so a wrong password fails here exactly as it
           would anywhere else. */
        form.querySelectorAll('[data-fill]').forEach((chip) => {
            chip.addEventListener('click', () => {
                email.value = chip.dataset.fill;
                document.getElementById('password').value = 'password';

                const dest = form.querySelector(`input[name="destination"][value="${chip.dataset.dest}"]`);
                if (dest) { dest.checked = true; syncDestination(); }

                const seat = document.getElementById('seat');
                if (seat && chip.dataset.seat) seat.value = chip.dataset.seat;

                document.getElementById('password').focus();
            });
        });
    })();
</script>
</x-layouts.marketing>
