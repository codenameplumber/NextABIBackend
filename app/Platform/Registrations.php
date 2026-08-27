<?php

namespace App\Platform;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Tenant;
use App\Mail\InvestorWelcome;
use App\Models\User;
use App\Support\Nigeria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;

/**
 * Two doors in, and one guarantee at the end of both.
 *
 *   A realtor's link  ->  the code arrives prefilled, attribution set at signup
 *   Direct            ->  no code, and the investor lands in the unassigned
 *                         queue for an admin to attach a realtor
 *
 * Nobody is turned away for arriving without a code. The guarantee is not "you
 * cannot register without a realtor" — it is that no instalment plan ever goes
 * live without one, and that is enforced at commitment approval instead.
 *
 * Two attribution axes get captured here and they answer different questions:
 * the realtor CODE is who brought them, and drives commission. HEARD ABOUT US
 * is which channel reached them, and drives marketing. A buyer can find the
 * platform on the radio and still be signed up by a realtor, so neither
 * substitutes for the other.
 */
class Registrations
{
    public function __construct(private Realtors $realtors) {}

    /**
     * The fields a registration collects, as validation rules.
     *
     * Kept here rather than in the controller because the questionnaire asks
     * most of the same questions, and two copies of this list drift.
     */
    public static function profileRules(): array
    {
        return [
            'state' => ['required', 'string', 'max:60'],
            'lga' => ['required', 'string', 'max:80'],
            'income_bracket' => ['required', 'string', 'in:'.implode(',', array_keys(Nigeria::INCOME_BRACKETS))],
            'occupation' => ['required', 'string', 'in:'.implode(',', array_keys(Nigeria::OCCUPATIONS))],
            'heard_about_us' => ['required', 'string', 'in:'.implode(',', array_keys(Nigeria::REFERRAL_SOURCES))],
        ];
    }

    /**
     * Register an investor.
     *
     * The Customer row and the User row are created together or not at all.
     * Half of a registration — a login with no investor record — produces an
     * account that signs in and then 403s, which is worse than a failed signup
     * because the person believes they have an account.
     */
    public function investor(Tenant $tenant, array $data, ?string $refCode = null): array
    {
        // An unknown or unapproved code is not an error. Someone mistyping a
        // code they were given should still end up registered, in the
        // unassigned queue, rather than blocked at the last step of a form.
        $realtor = $this->realtors->findByCode($refCode);

        if (! Nigeria::isLga($data['state'] ?? null, $data['lga'] ?? null)) {
            return ['ok' => false, 'message' => 'That local government is not in the state you picked.'];
        }

        $user = DB::transaction(function () use ($tenant, $data, $realtor) {
            $investor = Customer::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'agent_id' => $realtor?->id,
                'assigned_at' => $realtor ? now() : null,
                'state' => $data['state'],
                'lga' => $data['lga'],
                'income_bracket' => $data['income_bracket'],
                'occupation' => $data['occupation'],
                'heard_about_us' => $data['heard_about_us'],
                'source' => $realtor ? 'referral' : 'direct',
                'segment' => 'standard',
                'since' => now(),
                'ltv' => 0,
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'type' => 'investor',
                'role' => 'agent',
                'customer_id' => $investor->id,
                'is_client' => true,
                'email_verified_at' => now(),
            ]);

            // If this person already came through as a lead, close the loop so
            // the realtor's own list shows the enquiry converting rather than
            // leaving a ghost row that looks like it went nowhere.
            $this->closeMatchingLead($data['email'], $user, $realtor);

            return $user;
        });

        // Sent inline: somebody who just registered is still looking at the
        // screen, and a welcome that arrives ten minutes later has missed the
        // moment. Wrapped, because a mail failure must never cost somebody the
        // account they just created.
        try {
            Mail::to($user->email)->send(new InvestorWelcome($user, $realtor));
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'ok' => true,
            'user' => $user,
            'realtor' => $realtor,
            'message' => $realtor
                ? 'Welcome. '.$realtor->firstName().' is your realtor.'
                : 'Welcome. A realtor will be assigned to you shortly.',
        ];
    }

    /**
     * A questionnaire capture — the advert and shared-link path.
     *
     * Explicitly NOT a registration. Someone answering an advert is not ready
     * to choose a password, and demanding one is how a lead capture form gets
     * a two percent completion rate.
     */
    public function lead(Tenant $tenant, array $data, ?string $refCode = null): array
    {
        $realtor = $this->realtors->findByCode($refCode);

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'source' => $realtor ? 'Referral' : 'Website',
            'channel' => 'questionnaire',
            'heard_about_us' => $data['heard_about_us'] ?? null,
            'state' => $data['state'] ?? null,
            'lga' => $data['lga'] ?? null,
            'income_bracket' => $data['income_bracket'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'budget' => $data['budget'] ?? 0,
            'wants_location' => $data['wants_location'] ?? null,
            'wants_type' => $data['wants_type'] ?? null,
            'assigned_agent_id' => $realtor?->id,
            'status' => 'new',
            'max_stage' => 'new',
            'score' => $this->scoreLead($data),
            'is_open' => true,
            'last_contact_at' => now(),
        ]);

        return ['ok' => true, 'lead' => $lead, 'realtor' => $realtor,
            'message' => 'Thank you — someone will be in touch shortly.'];
    }

    /**
     * An opening score for a questionnaire capture.
     *
     * Budget and a stated preference are the two answers that actually predict
     * anything at first contact: someone who names a location and a number has
     * thought about it, someone who leaves both blank is browsing. Deliberately
     * coarse — the rescoring action recomputes this properly once there is
     * engagement to measure.
     */
    private function scoreLead(array $data): int
    {
        $score = 45;

        $budget = (float) ($data['budget'] ?? 0);
        $score += match (true) {
            $budget >= 100_000_000 => 25,
            $budget >= 50_000_000 => 18,
            $budget >= 20_000_000 => 10,
            $budget > 0 => 5,
            default => 0,
        };

        if (! empty($data['wants_location'])) {
            $score += 8;
        }

        if (! empty($data['phone'])) {
            $score += 7;
        }

        return max(3, min(95, $score));
    }

    /**
     * Attach a prior enquiry to the account it turned into.
     *
     * Matched on email, which is the only identifier both sides reliably carry.
     * Silent when there is no match — most registrations have no prior lead.
     */
    private function closeMatchingLead(string $email, User $user, ?Agent $realtor): void
    {
        Lead::where('email', $email)
            ->where('is_open', true)
            ->get()
            ->each(function (Lead $lead) use ($user, $realtor) {
                $lead->forceFill([
                    'converted_user_id' => $user->id,
                    'converted_at' => now(),
                    'status' => 'won',
                    'max_stage' => 'won',
                    'is_open' => false,
                    // Credit stays with whoever captured the lead if the
                    // registration itself carried no code.
                    'assigned_agent_id' => $lead->assigned_agent_id ?? $realtor?->id,
                ])->save();
            });
    }
}
