<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Tenant;
use Tests\TestCase;

/**
 * Multi-tenancy, tested at the boundary that matters.
 *
 * Every tenant-owned model carries a global scope reading the bound tenant.
 * The point of testing it here rather than trusting it is that the failure
 * mode is silent: a forgotten `where` does not error, it just shows one
 * agency another agency's pipeline.
 */
class IsolationTest extends TestCase
{
    public function test_a_second_tenants_rows_are_invisible(): void
    {
        $mine = Lead::count();
        $this->assertGreaterThan(0, $mine);

        $other = Tenant::create([
            'name' => 'Harbour Estates', 'slug' => 'harbour-'.uniqid(),
            'city' => 'Abuja', 'currency' => 'NGN', 'settings' => [],
        ]);

        Lead::insert([
            'tenant_id' => $other->id, 'name' => 'Somebody Elses Lead',
            'email' => 'other@example.com', 'source' => 'Website', 'channel' => 'Organic',
            'status' => 'new', 'max_stage' => 'new', 'score' => 50, 'budget' => 1e8,
            'wants_location' => 'Maitama', 'wants_type' => 'Apartment',
            'is_open' => true, 'created_at' => now(), 'updated_at' => now(),
            'last_contact_at' => now(),
        ]);

        // Still bound to Anchorstone: the row exists but is not ours.
        $this->assertSame($mine, Lead::count(), 'Another agency\'s leads leaked into this tenant.');
        $this->assertNull(Lead::where('name', 'Somebody Elses Lead')->first());

        app()->instance('nexabi.tenant', $other);
        $this->assertSame(1, Lead::count());
        $this->assertNotNull(Lead::where('name', 'Somebody Elses Lead')->first());
    }

    public function test_new_rows_are_stamped_with_the_bound_tenant(): void
    {
        $lead = Lead::create([
            'name' => 'Stamped Lead', 'email' => 'stamped@example.com',
            'source' => 'Referral', 'channel' => 'Referral', 'status' => 'new',
            'max_stage' => 'new', 'score' => 60, 'budget' => 5e7,
            'wants_location' => 'Lekki', 'wants_type' => 'Apartment',
            'is_open' => true, 'last_contact_at' => now(),
        ]);

        $this->assertSame($this->tenant->id, $lead->tenant_id);
    }

    public function test_staff_cannot_be_sent_to_the_client_portal(): void
    {
        $this->actingAs($this->staff())
            ->get('/portal')
            ->assertRedirect(route('app.dashboard'));
    }

    public function test_a_client_cannot_reach_the_console(): void
    {
        $this->actingAs($this->client())
            ->get('/app')
            ->assertRedirect(route('portal.overview'));
    }

    public function test_a_signed_out_visitor_is_sent_to_login(): void
    {
        $this->get('/app')->assertRedirect(route('login'));
        $this->get('/portal')->assertRedirect(route('login'));
    }

    public function test_the_portal_only_ever_shows_the_signed_in_buyers_own_payments(): void
    {
        $client = $this->client();
        $response = $this->actingAs($client)->get('/portal/payments');

        $response->assertOk();

        $mine = \App\Models\Payment::where('customer_id', $client->customer_id)->count();
        $this->assertGreaterThan(0, $mine, 'The demo buyer has no instalments to show.');

        // Nobody else's name appears anywhere on the page. A portal that leaks
        // one customer's schedule to another is worse than no portal.
        $others = \App\Models\Customer::whereKeyNot($client->customer_id)
            ->orderByDesc('ltv')->limit(8)->pluck('name');

        foreach ($others as $name) {
            $response->assertDontSee($name, escape: false);
        }
    }
}
