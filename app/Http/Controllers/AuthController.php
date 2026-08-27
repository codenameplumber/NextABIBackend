<?php

namespace App\Http\Controllers;

use App\Engine\InsightEngine;
use App\Mail\AgencyWelcome;
use App\Models\Tenant;
use App\Models\User;
use App\Platform\Registrations;
use App\Platform\Realtors;
use App\Support\Nigeria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        // The demo accounts used to be listed here as one-click chips. They are
        // documented in the README instead: a sign-in page that hands out six
        // working logins reads as a demo rather than as a product, and the
        // chips were the loudest thing on the screen.
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'seat' => ['nullable', 'string'],
        ]);

        $credentials = ['email' => $data['email'], 'password' => $data['password']];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Those details do not match any account.',
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();

        /**
         * The seat carries into the console, when something sends one.
         *
         * The sign-in form no longer asks. A lens is a preference, not a
         * credential, and putting it on the door made signing in look like a
         * configuration step — so the account's own `role` ranks the feed and
         * whoever wants a different lens changes it inside the console.
         *
         * The handling stays because the session key is still the thing the
         * role switcher writes, and because a nullable seat arriving from
         * anywhere else should still be honoured rather than silently dropped.
         */
        if (! $user->isStaff()) {
            $request->session()->forget('nexabi.role');
        } elseif (($seat = $data['seat'] ?? null) && array_key_exists($seat, InsightEngine::ROLES)) {
            $request->session()->put('nexabi.role', $seat);
        }

        /**
         * Where they belong.
         *
         * The form used to ask people to declare this and then ignored the
         * answer, which was theatre while there were two kinds of account and
         * actively misleading once there were three. The account already knows,
         * so it decides — and a realtor still waiting on approval lands on the
         * holding screen rather than a dead end.
         */
        $home = $this->homeFor($user);

        return redirect()->intended($home);
    }

    /* ----------------------------------------------------- registration */

    /**
     * Investor registration.
     *
     * ?ref=CODE arrives from a realtor's link and is shown back to the person
     * rather than hidden — "Chidi is registering you" is reassurance when it is
     * right, and a correctable mistake when it is not.
     */
    public function showRegister(Request $request)
    {
        return view('auth.register', [
            'realtor' => app(Realtors::class)->findByCode($request->query('ref')),
            'ref' => $request->query('ref'),
            'states' => Nigeria::stateNames(),
            'lgaMap' => Nigeria::STATES,
            'incomes' => Nigeria::INCOME_BRACKETS,
            'occupations' => Nigeria::OCCUPATIONS,
            'sources' => Nigeria::REFERRAL_SOURCES,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], Registrations::profileRules()));

        if (! $tenant = Tenant::first()) {
            return back()->withErrors(['email' => 'The platform is not set up yet.']);
        }

        $result = app(Registrations::class)->investor($tenant, $data, $request->input('ref'));

        if (! $result['ok']) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['lga' => $result['message']]);
        }

        Auth::login($result['user']);
        $request->session()->regenerate();

        return redirect()->route('portal.browse')->with('status', $result['message']);
    }

    /* --------------------------------------------------------- realtors */

    public function showRealtorApply()
    {
        return view('auth.realtor-apply');
    }

    /**
     * A realtor signs themselves up, and then waits.
     *
     * Logged in straight away on purpose: the holding screen is worth seeing,
     * because it says what happens next and roughly when. An application that
     * ends on a blank page reads as one that went nowhere.
     */
    public function realtorApply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $tenant = Tenant::first()) {
            return back()->withErrors(['email' => 'The platform is not set up yet.']);
        }

        $result = app(Realtors::class)->register($tenant, $data);

        Auth::login($result['user']);
        $request->session()->regenerate();

        return redirect()->route('realtor.pending')->with('status', $result['message']);
    }

    /**
     * Sign out, and land on the sign-in page.
     *
     * It used to return to the marketing home, which left somebody who had
     * just pressed "Sign out" looking at a page selling them the product —
     * with no confirmation anything had happened and no obvious way back in.
     * Somebody signing out is almost always either finished or switching
     * accounts, and both of those want this screen.
     *
     * The flash is the part that matters: without it the redirect is
     * indistinguishable from the session having quietly expired.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'You have been signed out.');
    }

    /**
     * Switch the lens.
     *
     * This is not a permission change — it re-weights the same findings for a
     * different job. A Finance Lead and a Sales Manager looking at one
     * business should not be handed the same top three.
     */
    public function switchRole(Request $request): RedirectResponse
    {
        $role = $request->input('role');

        if (array_key_exists($role, InsightEngine::ROLES)) {
            $request->session()->put('nexabi.role', $role);
        }

        return back();
    }

    /**
     * Where this account belongs.
     *
     * Asks the user rather than branching on staff-or-not: there are three
     * kinds of account now, and a two-way branch silently sends realtors to
     * the investor portal.
     */
    private function homeFor(User $user): string
    {
        return route($user->homeRoute());
    }
}
