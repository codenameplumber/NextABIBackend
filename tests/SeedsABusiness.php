<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
            // The real seeder, not a copy of it. A fixture that builds its own
            // accounts is a fixture that can pass while the shipped dataset is
            // broken — which is the one thing a test suite must not do.
            Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

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
