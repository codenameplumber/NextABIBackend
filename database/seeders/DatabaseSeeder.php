<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BusinessSimulator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** One password across the demo accounts, printed on the login page. */
    public const PASSWORD = 'password';

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

        /* -------------------------------------------------- the platform
           The simulator models a business; this wraps the product around it —
           frozen commission rates on historical sales, a live approval queue,
           a few investors waiting on a realtor, and materials to send. */

        $platform = (new \App\Support\PlatformSeeder($tenant))->run();

        foreach ($platform as $k => $v) {
            $this->command->line(sprintf("  %-12s %s", $k, number_format($v)));
        }

        /* ------------------------------------------------------- realtors
           Every realtor needs a referral code before they can bring anyone in
           — it is the column the whole commission model hangs off. The
           simulator does not mint them because a code is issued at approval,
           which is an act, not a property of a seeded row.

           Two are left pending on purpose, so the approval queue an admin
           works through has something in it on the first morning. */

        $realtors = Agent::orderBy("id")->get();

        foreach ($realtors as $i => $realtor) {
            $pending = $i >= $realtors->count() - 2;

            $realtor->forceFill([
                "status" => $pending ? "pending" : "approved",
                "code" => $pending ? null : ($realtor->code ?: Agent::generateCode($realtor->name)),
                "approved_at" => $pending ? null : now(),
                "phone" => "080".str_pad((string) (10000000 + $realtor->id), 8, "0", STR_PAD_LEFT),
            ])->save();
        }

        $this->command->line(sprintf("  %-12s %s approved · 2 awaiting approval",
            "realtors", $realtors->count() - 2));

        /* ---------------------------------------------------------- staff
           Six seats across the four roles, so the role switcher and the digest
           can be checked against real people rather than one account wearing
           four hats. Names are drawn from the agents the simulator created, so
           the person who signs in is the same person who appears in the team
           table and on the leads they own. */

        $agents = Agent::orderBy('id')->limit(6)->get();

        $seats = [
            ['ada',   'ceo',     'CEO / Managing Director'],
            ['tunde', 'sales',   'Sales Manager'],
            ['ngozi', 'finance', 'Finance Lead'],
            ['segun', 'agent',   'Sales Agent'],
            ['funke', 'sales',   'Sales Manager · Island'],
            ['ibrahim', 'agent', 'Sales Agent · Mainland'],
        ];

        $logins = [];

        foreach ($seats as $i => [$handle, $role, $label]) {
            $agent = $agents[$i] ?? null;
            $email = $handle.'@anchorstone.ng';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $agent?->name ?? Str::headline($handle),
                    'role' => $role,
                    'type' => 'admin',
                    'agent_id' => $agent?->id,
                    'password' => Hash::make(self::PASSWORD),
                    'is_client' => false,
                    'email_verified_at' => now(),
                ],
            );

            // The agent record gets the same address, so a support ticket
            // raised against them actually reaches an inbox someone reads.
            $agent?->forceFill(['email' => $email])->save();

            $logins[] = [$email, $label, $user->name];
        }

        /* -------------------------------------------------- realtor logins
           Two seats, because the interesting half of the realtor experience is
           the half where you cannot do anything yet. An approved realtor has a
           code, a link and a book; a pending one has a holding screen — and a
           demo that only ever shows the first never shows the gate working. */

        $realtorSeats = [
            ["realtor@anchorstone.ng", "approved", "Realtor · approved"],
            ["pending@anchorstone.ng", "pending", "Realtor · awaiting approval"],
        ];

        foreach ($realtorSeats as [$email, $status, $label]) {
            // Skip anyone already wearing a staff seat — an admin login
                // and a realtor login must not point at the same person.
            $realtor = Agent::where("status", $status)
                ->whereNull("user_id")
                ->whereNotIn("id", User::whereNotNull("agent_id")->pluck("agent_id"))
                ->orderBy("id")->first();

            if (! $realtor) {
                continue;
            }

            $user = User::updateOrCreate(
                ["email" => $email],
                [
                    "tenant_id" => $tenant->id,
                    "name" => $realtor->name,
                    "role" => "agent",
                    "type" => "realtor",
                    "agent_id" => $realtor->id,
                    "is_client" => false,
                    "password" => Hash::make(self::PASSWORD),
                    "email_verified_at" => now(),
                ],
            );

            $realtor->forceFill(["user_id" => $user->id, "email" => $email])->save();

            $logins[] = [$email, $label, $realtor->name
                .($realtor->code ? "  ·  code ".$realtor->code : "")];
        }

        /* --------------------------------------------------------- clients
           Four buyers, chosen for four genuinely different files: one badly
           behind, one on schedule, one nearly done, one fully settled. A
           portal demo that only ever opens on a healthy account never shows
           what the product does when something is wrong. */

        $picks = [
            ['client@example.com',  'Buyer · behind on payments',  'overdue'],
            ['buyer2@example.com',  'Buyer · on schedule',         'current'],
            ['buyer3@example.com',  'Buyer · nearly settled',      'nearly'],
            ['buyer4@example.com',  'Buyer · fully settled',       'settled'],
        ];

        $taken = [];

        foreach ($picks as [$email, $label, $shape]) {
            $customer = $this->pickCustomer($shape, $taken);

            if (! $customer) {
                continue;
            }

            $taken[] = $customer->id;

            User::updateOrCreate(
                ['email' => $email],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $customer->name,
                    'role' => 'agent',
                    'type' => 'investor',
                    'customer_id' => $customer->id,
                    'is_client' => true,
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ],
            );

            $logins[] = [$email, $label, $customer->name];
        }

        $this->command->newLine();
        $this->command->info('Sign in with any of these — password: '.self::PASSWORD);

        foreach ($logins as [$email, $label, $name]) {
            $this->command->line(sprintf('  %-24s %-30s %s', $email, $label, $name));
        }
    }

    /**
     * Find a customer whose file demonstrates a particular situation.
     *
     * Falls back rather than failing: on a smaller dataset the same account
     * may have to stand in for two shapes, and a missing demo login is worse
     * than a slightly less varied one.
     */
    private function pickCustomer(string $shape, array $exclude): ?Customer
    {
        $base = fn () => Customer::query()
            ->whereNotIn('id', $exclude)
            ->withCount([
                'payments',
                'payments as unpaid' => fn ($q) => $q->whereNull('paid_at'),
                'payments as paid' => fn ($q) => $q->whereNotNull('paid_at'),
                'payments as late' => fn ($q) => $q->whereNull('paid_at')
                    ->where('due_at', '<', now()->subDays(30)),
            ]);

        $found = match ($shape) {
            // Behind on payments, but not someone who has never paid at all.
            // A buyer with nothing settled has no receipt to download and no
            // progress bar worth looking at — the account is meant to
            // demonstrate a journey that went wrong partway, not one that
            // never started. Falls back if the book holds nobody like that.
            'overdue' => $base()->having('late', '>', 0)->having('paid', '>', 0)
                ->orderByDesc('ltv')->first()
                ?? $base()->having('late', '>', 0)->orderByDesc('ltv')->first(),
            'current' => $base()->having('unpaid', '>', 1)->having('late', '=', 0)
                ->having('paid', '>', 0)->orderByDesc('ltv')->first(),
            'nearly' => $base()->having('unpaid', '=', 1)->having('paid', '>', 1)
                ->orderByDesc('ltv')->first(),
            'settled' => $base()->having('payments_count', '>', 1)->having('unpaid', '=', 0)
                ->orderByDesc('ltv')->first(),
            default => null,
        };

        return $found
            ?? $base()->having('payments_count', '>', 0)->orderByDesc('ltv')->first();
    }
}
