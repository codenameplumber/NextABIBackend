<?php

namespace App\Platform;

use App\Mail\RealtorApproved;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Realtors: signing up, being approved, and owning investors.
 *
 * A realtor is a person who logs in and an agent row in the same breath. Those
 * were two separate rows joined by a matching email before the pivot, which
 * worked only because a seeder wrote both. Someone signing up at two in the
 * morning needs them created together or not at all.
 *
 * The referral code is the spine of the commercial model — it is how the
 * platform knows who brought a buyer in, and therefore who gets paid. It is
 * issued at approval, not at signup, because an unapproved realtor handing out
 * links would be collecting registrations nobody has vetted.
 */
class Realtors
{
    /**
     * Self-signup. Creates the login and the realtor record, pending.
     *
     * No code is issued here. Until an admin approves them the account exists,
     * can sign in, and can see exactly one holding screen.
     */
    public function register(Tenant $tenant, array $data): array
    {
        $user = DB::transaction(function () use ($tenant, $data) {
            $realtor = Agent::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'role' => 'Realtor',
                'team' => $data['team'] ?? null,
                'tier' => 'core',
                'skill' => 0.600,
                'active' => true,
                'status' => 'pending',
                'joined_at' => now(),
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'type' => 'realtor',
                'role' => 'agent',
                'agent_id' => $realtor->id,
                'is_client' => false,
                'email_verified_at' => now(),
            ]);

            $realtor->forceFill(['user_id' => $user->id])->save();

            return $user;
        });

        return ['ok' => true, 'user' => $user,
            'message' => 'Application received. You will hear from us once it is reviewed.'];
    }

    /**
     * An admin approves them, and the code is issued.
     *
     * Idempotent on the code: approving an already-approved realtor must not
     * mint a second one, because the first is already printed on flyers and
     * pasted into WhatsApp messages that will keep arriving for months.
     */
    public function approve(Agent $realtor, User $admin): array
    {
        if ($realtor->isApproved()) {
            return ['ok' => false, 'message' => 'That realtor is already approved.'];
        }

        $realtor->forceFill([
            'status' => 'approved',
            'code' => $realtor->code ?: Agent::generateCode($realtor->name),
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'active' => true,
        ])->save();

        // The email the holding screen promised, carrying the code and the
        // link — the two things they have actually been waiting for.
        try {
            Mail::to($realtor->email)->send(new RealtorApproved($realtor->fresh()));
        } catch (\Throwable $e) {
            report($e);
        }

        return ['ok' => true, 'realtor' => $realtor->fresh(),
            'message' => $realtor->firstName().' approved · code '.$realtor->code];
    }

    public function suspend(Agent $realtor, User $admin): array
    {
        if ($realtor->status === 'suspended') {
            return ['ok' => false, 'message' => 'That realtor is already suspended.'];
        }

        // The code is kept. Links already in circulation keep resolving to a
        // real person, and their existing investors keep their owner — a
        // suspension is not a reason to orphan a book of business.
        $realtor->forceFill(['status' => 'suspended', 'active' => false])->save();

        return ['ok' => true, 'message' => $realtor->firstName().' suspended.'];
    }

    /**
     * Attach a realtor to an investor who arrived without a code.
     *
     * This is the unassigned queue, and clearing it is what makes an investor's
     * first commitment approvable.
     */
    public function assign(Customer $investor, Agent $realtor, User $admin): array
    {
        if (! $realtor->isApproved()) {
            return ['ok' => false, 'message' => 'That realtor is not approved.'];
        }

        if ($investor->isAssigned()) {
            return ['ok' => false, 'message' => $investor->firstName().' already has a realtor.'];
        }

        $investor->forceFill([
            'agent_id' => $realtor->id,
            'assigned_at' => now(),
            'assigned_by' => $admin->id,
        ])->save();

        return ['ok' => true,
            'message' => $investor->firstName().' assigned to '.$realtor->name];
    }

    /** Resolve a referral code typed, pasted or arriving in a link. */
    public function findByCode(?string $code): ?Agent
    {
        if (blank($code)) {
            return null;
        }

        // Codes are dictated aloud and typed from memory, so the lookup is
        // forgiving about case and stray spacing. It is not forgiving about
        // approval — an unapproved code resolves to nothing.
        return Agent::query()
            ->approved()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->first();
    }
}
