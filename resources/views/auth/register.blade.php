<x-layouts.marketing title="Create an account — NexABI"
                     description="Set up your agency on NexABI. Import your data, or explore a seeded book first.">

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

            <h1 class="display--sm">Create your account</h1>
            <p class="lede mt-8" style="font-size:var(--t-md)">
                One account, one agency. Everything you add stays inside it.
            </p>

            <div class="demo-note mt-24">
                <span data-icon="shield" data-size="15"></span>
                <span>
                    <b>Your data is yours.</b> Every record is stamped with your agency and every
                    query is scoped to it at the database layer — not by a filter someone can
                    forget to write.
                </span>
            </div>

            <form method="POST" action="{{ route('register.attempt') }}" novalidate id="regForm">
                @csrf

                <div class="field mt-20">
                    <label>What are you signing up as?</label>
                    <div class="role-pick">
                        <label class="role-opt">
                            <input type="radio" name="kind" value="agency"
                                   {{ old('kind', 'agency') === 'agency' ? 'checked' : '' }}>
                            <span class="role-opt__t">
                                <span data-icon="chart" data-size="16"></span>An agency
                            </span>
                            <span class="role-opt__d">I sell or manage property and want the intelligence layer</span>
                        </label>
                        <label class="role-opt">
                            <input type="radio" name="kind" value="client"
                                   {{ old('kind') === 'client' ? 'checked' : '' }}>
                            <span class="role-opt__t">
                                <span data-icon="home" data-size="16"></span>A buyer
                            </span>
                            <span class="role-opt__d">I'm purchasing and want to track my property and payments</span>
                        </label>
                    </div>
                </div>

                <div class="field {{ $errors->has('name') ? 'is-invalid' : '' }}" id="fName">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           autocomplete="name" placeholder="Adaeze Okafor" required>
                    @error('name')<span class="field__err">{{ $message }}</span>@enderror
                </div>

                <div class="field {{ $errors->has('agency') ? 'is-invalid' : '' }}" id="fOrg">
                    <label for="agency">Company</label>
                    <input type="text" id="agency" name="agency" value="{{ old('agency') }}"
                           autocomplete="organization" placeholder="Anchorstone Properties">
                    @error('agency')<span class="field__err">{{ $message }}</span>@enderror
                </div>

                <div class="field {{ $errors->has('email') ? 'is-invalid' : '' }}" id="fEmail">
                    <label for="email">Work email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           autocomplete="email" placeholder="you@agency.com" required>
                    @error('email')<span class="field__err">{{ $message }}</span>@enderror
                </div>

                <div class="field {{ $errors->has('password') ? 'is-invalid' : '' }}" id="fPass">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           autocomplete="new-password" placeholder="At least 8 characters" required>
                    <span class="field__hint" id="strength">Use 8 characters or more.</span>
                    @error('password')<span class="field__err">{{ $message }}</span>@enderror
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           autocomplete="new-password" placeholder="Type it again" required>
                </div>

                <button class="btn btn--primary btn--lg btn--full mt-24" type="submit">
                    <span>Create account</span>
                    <span data-icon="arrow" data-size="16"></span>
                </button>

                <div class="auth-alt">
                    Already have an account? <a href="{{ route('login') }}">Sign in</a>
                    &nbsp;·&nbsp;
                    <a href="{{ route('home') }}">Back to the site</a>
                </div>
            </form>
        </div>
    </div>

    <aside class="auth-aside grain">
        <div>
            <span class="kicker">What happens next</span>

            <h2 class="display--sm mt-16" style="max-width:17ch">
                Point it at your book and let it read.
            </h2>

            <p class="lede mt-16" style="font-size:var(--t-md);max-width:44ch">
                Import leads, properties, deals and payments as CSV — or load the sample Lagos
                agency first and see what the detectors make of it before you trust them with
                the real thing.
            </p>

            <div class="shot mt-32" style="max-width:520px">
                <span class="shot-tag"><span class="pulse"></span>Live</span>
                <img src="{{ asset('img/shot-dashboard.png') }}"
                     alt="The NexABI console showing an AI briefing, four KPI tiles and a ranked insight feed"
                     width="1568" height="897" loading="lazy">
                <div class="shot-cap">
                    <span data-icon="insight" data-size="14" style="color:var(--brand-500)"></span>
                    The dashboard, rendered from live data
                </div>
            </div>

            <div class="ed-figures mt-32" style="max-width:520px">
                <div><div class="n">9</div><div class="k">Entities modelled</div></div>
                <div><div class="n">18</div><div class="k">Executable actions</div></div>
                <div><div class="n">1</div><div class="k">Closed loop</div></div>
            </div>
        </div>
    </aside>
</div>

<script type="module" src="{{ asset('js/site.js') }}"></script>
<script type="module" src="{{ asset('js/password-strength.js') }}"></script>
<script>
    (function () {
        const form = document.getElementById('regForm');
        const orgField = document.getElementById('fOrg');
        const email = document.getElementById('email');
        const emailLabel = form.querySelector('label[for="email"]');

        /* A buyer does not name a company — they are attached to one that
           already exists. Hiding the field is kinder than validating it away. */
        function syncKind() {
            const kind = form.querySelector('input[name="kind"]:checked').value;
            orgField.style.display = kind === 'agency' ? '' : 'none';
            email.placeholder = kind === 'agency' ? 'you@agency.com' : 'you@example.com';
            emailLabel.textContent = kind === 'agency' ? 'Work email' : 'Email address';
        }

        form.querySelectorAll('input[name="kind"]')
            .forEach((r) => r.addEventListener('change', syncKind));
        syncKind();
    })();
</script>
</x-layouts.marketing>
