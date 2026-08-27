<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Property;
use App\Models\User;
use Tests\TestCase;

/**
 * Every surface renders, for the kind of person meant to see it.
 *
 * A render test is shallow on purpose. It does not check that a page is
 * *right* — the other suites do that — it checks that it does not 500, which is
 * the failure that makes every other test irrelevant.
 */
class PlatformSmokeTest extends TestCase
{
    private function admin(): User
    {
        return User::where('type', 'admin')->firstOrFail();
    }

    private function investor(): User
    {
        return User::where('type', 'investor')->firstOrFail();
    }

    private function approvedRealtor(): User
    {
        $user = User::where('type', 'realtor')->firstOrFail();

        Agent::whereKey($user->agent_id)->update([
            'status' => 'approved',
            'code' => 'SMOKE1',
        ]);

        return $user->fresh();
    }

    /* -------------------------------------------------------------- public */

    public function test_the_public_pages_render_for_a_stranger(): void
    {
        foreach (['home', 'properties', 'enquire', 'register', 'realtor.apply', 'login'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_a_property_page_renders_without_signing_in(): void
    {
        $property = Property::query()->onMarket()->firstOrFail();

        $this->get(route('property', $property->ref))
            ->assertOk()
            ->assertSee($property->title)
            // The monthly figure is the whole question a buyer has.
            ->assertSee('month', false);
    }

    public function test_the_catalogue_never_shows_what_is_already_sold(): void
    {
        $sold = Property::where('status', 'sold')->first();

        if (! $sold) {
            $this->markTestSkipped('No sold stock in the fixture.');
        }

        // Asserted on the link rather than the reference: the search term is
        // echoed back into the filter box, so the string itself is on the page
        // whether or not the property is.
        $this->get(route('properties').'?q='.urlencode($sold->ref))
            ->assertOk()
            ->assertDontSee(route('property', $sold->ref));

        $this->assertFalse(
            Property::query()->onMarket()->whereKey($sold->id)->exists(),
            'A sold unit must be out of the catalogue entirely.',
        );
    }

    public function test_an_unlisted_property_is_hidden_even_while_available(): void
    {
        $property = Property::query()->onMarket()->firstOrFail();
        $property->forceFill(['listed' => false])->save();

        // Withdrawing a listing has to take it off the market without
        // pretending it is sold — those are different states.
        $this->assertFalse(Property::query()->onMarket()->whereKey($property->id)->exists());

        $this->get(route('properties'))
            ->assertOk()
            ->assertDontSee(route('property', $property->ref));
    }

    /* ------------------------------------------------------------- console */

    public function test_every_console_page_renders_for_an_admin(): void
    {
        $routes = ['dashboard', 'insights', 'ask', 'sales', 'commitments', 'leads',
            'properties', 'marketing', 'customers', 'finance', 'realtors',
            'materials', 'analytics', 'reports', 'activity', 'settings'];

        foreach ($routes as $route) {
            $this->actingAs($this->admin())
                ->get(route('app.'.$route))
                ->assertOk();
        }
    }

    public function test_feedback_is_gone_from_the_console(): void
    {
        // Removed from the menu, but the data is still collected at journey
        // milestones — two detectors run on it.
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('app.feedback'),
            'Feedback should no longer be a console destination.',
        );
    }

    /* ------------------------------------------------------------ realtors */

    public function test_every_realtor_page_renders_once_approved(): void
    {
        $user = $this->approvedRealtor();

        foreach (['dashboard', 'leads', 'properties', 'materials', 'commission'] as $route) {
            $this->actingAs($user)->get(route('realtor.'.$route))->assertOk();
        }
    }

    public function test_a_pending_realtor_sees_the_holding_screen_and_nothing_else(): void
    {
        $user = User::where('type', 'realtor')->firstOrFail();

        Agent::whereKey($user->agent_id)->update(['status' => 'pending', 'code' => null]);
        $user = $user->fresh();

        $this->actingAs($user)->get(route('realtor.pending'))->assertOk();

        // Everything else bounces back to the gate.
        foreach (['dashboard', 'leads', 'properties', 'materials', 'commission'] as $route) {
            $this->actingAs($user)
                ->get(route('realtor.'.$route))
                ->assertRedirect(route('realtor.pending'));
        }
    }

    public function test_an_approved_realtor_is_not_left_on_the_holding_screen(): void
    {
        $this->actingAs($this->approvedRealtor())
            ->get(route('realtor.pending'))
            ->assertRedirect(route('realtor.dashboard'));
    }

    /* ----------------------------------------------------------- investors */

    public function test_the_investor_pages_render(): void
    {
        foreach (['overview', 'browse', 'payments', 'documents', 'support'] as $route) {
            $this->actingAs($this->investor())->get(route('portal.'.$route))->assertOk();
        }
    }

    public function test_the_commit_page_renders_for_an_investor(): void
    {
        $property = Property::query()->onMarket()->firstOrFail();

        $this->actingAs($this->investor())
            ->get(route('portal.commit', $property->ref))
            ->assertOk()
            ->assertSee($property->title);
    }

    /* ----------------------------------------------------------- isolation */

    public function test_each_kind_of_account_is_kept_to_its_own_area(): void
    {
        // The new tenancy: role against role rather than agency against agency.
        $this->actingAs($this->investor())->get(route('app.dashboard'))
            ->assertRedirect(route('portal.overview'));

        $this->actingAs($this->investor())->get(route('realtor.dashboard'))
            ->assertRedirect(route('portal.overview'));

        $this->actingAs($this->approvedRealtor())->get(route('app.dashboard'))
            ->assertRedirect(route('realtor.dashboard'));

        $this->actingAs($this->approvedRealtor())->get(route('portal.overview'))
            ->assertRedirect(route('realtor.dashboard'));

        $this->actingAs($this->admin())->get(route('portal.overview'))
            ->assertRedirect(route('app.dashboard'));

        $this->actingAs($this->admin())->get(route('realtor.dashboard'))
            ->assertRedirect(route('app.dashboard'));
    }

    public function test_a_realtor_only_ever_sees_their_own_leads(): void
    {
        $user = $this->approvedRealtor();

        $someoneElse = \App\Models\Lead::whereNotNull('assigned_agent_id')
            ->where('assigned_agent_id', '!=', $user->agent_id)
            ->whereNotNull('name')
            ->first();

        if (! $someoneElse) {
            $this->markTestSkipped('No other realtor holds leads in the fixture.');
        }

        $this->actingAs($user)
            ->get(route('realtor.leads'))
            ->assertOk()
            ->assertDontSee($someoneElse->name);
    }

    /* ---------------------------------------------------------- sign out */

    public function test_signing_out_lands_on_the_sign_in_page_and_says_so(): void
    {
        // It used to return to the marketing home, which left somebody who had
        // just pressed "Sign out" looking at a page selling them the product,
        // with no confirmation anything had happened.
        foreach (['ada@anchorstone.ng', 'realtor@anchorstone.ng', 'client@example.com'] as $email) {
            $user = \App\Models\User::where('email', $email)->firstOrFail();

            $this->actingAs($user)
                ->post(route('logout'))
                ->assertRedirect(route('login'))
                ->assertSessionHas('status');

            // assertGuest's first argument is a GUARD name, not a message.
            $this->assertFalse(
                auth()->check(),
                "{$email} was still signed in after logging out.",
            );
        }
    }

    /**
     * The portal shares the catalogue markup with the public site.
     *
     * Those styles lived in public.css, which the portal layout does not load —
     * so /portal/browse rendered the right markup with no grid, no card and no
     * image ratio behind it. They belong in components.css, which every layout
     * loads, and this fails if anybody moves them back.
     */
    public function test_the_portal_catalogue_has_styles_behind_its_markup(): void
    {
        $buyer = \App\Models\User::where('email', 'client@example.com')->firstOrFail();

        $html = $this->actingAs($buyer)->get(route('portal.browse'))->assertOk()->getContent();

        $shared = ['pgrid', 'pcard', 'pcard__b', 'pcard__price', 'filters'];

        foreach ($shared as $class) {
            $this->assertStringContainsString('class="'.$class, $html,
                "The browse page stopped using .{$class}.");
        }

        $css = file_get_contents(public_path('css/components.css'));

        $this->assertStringContainsString('components.css',
            file_get_contents(resource_path('views/layouts/portal.blade.php')));

        foreach ($shared as $class) {
            $this->assertStringContainsString('.'.$class.' {', $css,
                ".{$class} is not in the stylesheet every layout loads.");
        }
    }
}
