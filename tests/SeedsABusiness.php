<?php

namespace Tests;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BusinessSimulator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds one full agency, once, for the whole suite.
 *
 * Twenty months of simulated business is expensive to generate, and every test
 * here is about reading it rather than writing it. So the tables are built and
 * seeded on the first test that needs them and each subsequent test runs
 * inside a transaction that is rolled back — the same data, a clean slate, and
 * one simulation instead of thirty.
 */
trait SeedsABusiness
{
    private static bool $seeded = false;

    protected Tenant $tenant;

    protected function setUpBusiness(): void
    {
        if (! self::$seeded) {
            Artisan::call('migrate:fresh', ['--force' => true]);

            $tenant = Tenant::create([
                'name' => 'Anchorstone Properties',
                'slug' => 'anchorstone',
                'city' => 'Lagos',
                'currency' => 'NGN',
                'settings' => ['policies' => []],
            ]);

            app()->instance('nexabi.tenant', $tenant);
            (new BusinessSimulator($tenant))->run();

            foreach ([
                ['Ada Okonkwo', 'ada@anchorstone.ng', 'ceo'],
                ['Ngozi Eze', 'ngozi@anchorstone.ng', 'finance'],
            ] as [$name, $email, $role]) {
                User::create([
                    'tenant_id' => $tenant->id, 'name' => $name, 'email' => $email,
                    'role' => $role, 'password' => Hash::make('password'),
                    'is_client' => false, 'email_verified_at' => now(),
                ]);
            }

            $customer = Customer::query()
                ->withCount(['payments as unpaid' => fn ($q) => $q->whereNull('paid_at')])
                ->having('unpaid', '>', 0)->orderByDesc('ltv')->first();

            User::create([
                'tenant_id' => $tenant->id, 'name' => $customer->name,
                'email' => 'client@example.com', 'role' => 'agent',
                'customer_id' => $customer->id, 'is_client' => true,
                'password' => Hash::make('password'), 'email_verified_at' => now(),
            ]);

            self::$seeded = true;
        }

        $this->tenant = Tenant::where('slug', 'anchorstone')->firstOrFail();
        app()->instance('nexabi.tenant', $this->tenant);

        // Each test gets the same rows and leaves none of its own behind.
        DB::beginTransaction();
    }

    protected function tearDownBusiness(): void
    {
        DB::rollBack();
    }

    protected function staff(string $email = 'ada@anchorstone.ng'): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function client(): User
    {
        return User::where('email', 'client@example.com')->firstOrFail();
    }
}
