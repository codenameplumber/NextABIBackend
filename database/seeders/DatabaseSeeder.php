<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BusinessSimulator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'anchorstone'],
            [
                'name' => 'Anchorstone Properties',
                'city' => 'Lagos',
                'currency' => 'NGN',
                'settings' => ['policies' => []],
            ],
        );

        // Bind the tenant so the global scope and the creating() hook see it
        // while seeding — there is no authenticated user in a console run.
        app()->instance('nexabi.tenant', $tenant);

        $this->command->info('Simulating ~20 months of business…');
        $counts = (new BusinessSimulator($tenant))->run();

        foreach ($counts as $k => $v) {
            $this->command->line(sprintf('  %-12s %s', $k, number_format($v)));
        }

        /* ---------------------------------------------------------- staff */

        $staff = [
            ['Ada Okonkwo',   'ada@anchorstone.ng',   'ceo'],
            ['Tunde Balogun', 'tunde@anchorstone.ng', 'sales'],
            ['Ngozi Eze',     'ngozi@anchorstone.ng', 'finance'],
            ['Segun Adeyemi', 'segun@anchorstone.ng', 'agent'],
        ];

        foreach ($staff as [$name, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                    'role' => $role,
                    'password' => Hash::make('password'),
                    'is_client' => false,
                    'email_verified_at' => now(),
                ],
            );
        }

        /* ---------------------------------------------------------- client
           Give the demo buyer an account whose file is worth opening: a
           multi-instalment plan, partly paid, still running. A portal that
           opens on a fully settled account shows nothing. */

        $customer = Customer::query()
            ->withCount([
                'payments as unpaid_count' => fn ($q) => $q->whereNull('paid_at'),
                'payments as paid_count' => fn ($q) => $q->whereNotNull('paid_at'),
            ])
            ->having('unpaid_count', '>', 0)
            ->having('paid_count', '>', 0)
            ->orderByDesc('ltv')
            ->first()
            ?? Customer::orderByDesc('ltv')->first();

        if ($customer) {
            User::updateOrCreate(
                ['email' => 'client@example.com'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $customer->name,
                    'role' => 'agent',
                    'customer_id' => $customer->id,
                    'is_client' => true,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
            $this->command->line('  client user  '.$customer->name.' (client@example.com)');
        }

        $this->command->newLine();
        $this->command->info('Sign in with any of these — password: password');
        $this->command->line('  ada@anchorstone.ng     CEO / Managing Director');
        $this->command->line('  tunde@anchorstone.ng   Sales Manager');
        $this->command->line('  ngozi@anchorstone.ng   Finance Lead');
        $this->command->line('  client@example.com     Buyer (client portal)');
    }
}
