<?php

namespace App\Platform;

use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Committing to a property, and what an admin does about it.
 *
 * The commercial core. Three operations, and each one is the only place its
 * rule is enforced:
 *
 *   open()      an investor commits — the unit is HELD, nothing is sold
 *   approve()   the plan is generated and the commission rate is FROZEN
 *   decline()   the hold is released
 *
 * Approval is deliberately a separate step from commitment. It is where a human
 * checks that the investor has a realtor attached, and it is the only moment
 * the commission rate is read off the property. After that the rate on the
 * commitment is the truth, for ever — editing a listing next year must never
 * rewrite commission that has already been earned.
 */
class Commitments
{
    /**
     * An investor commits to a property.
     *
     * Refuses rather than throws, because every refusal here is something the
     * investor can read and act on: a unit taken while they were deciding, a
     * duration outside what this property offers, a commitment they already
     * hold on the same unit.
     */
    public function open(Customer $investor, Property $property, int $months): array
    {
        if (! $property->isOnMarket()) {
            return $this->no('That property is no longer available.');
        }

        if (! $property->allowsDuration($months)) {
            return $this->no('That instalment term is not offered on this property.');
        }

        $existing = Deal::where('customer_id', $investor->id)
            ->where('property_id', $property->id)
            ->whereIn('status', array_merge(['pending'], Deal::LIVE))
            ->exists();

        if ($existing) {
            return $this->no('You already hold a commitment on this property.');
        }

        $commitment = DB::transaction(function () use ($investor, $property, $months) {
            $commitment = Deal::create([
                'tenant_id' => $investor->tenant_id,
                'customer_id' => $investor->id,
                'property_id' => $property->id,
                // Credit follows the investor's realtor, which may still be
                // null here — approval is what refuses to proceed without one.
                'agent_id' => $investor->agent_id,
                'value' => $property->price,
                'commission' => 0,
                'duration_months' => $months,
                'status' => 'pending',
                'won' => false,
                'opened_at' => now(),
                'committed_at' => now(),
                'source' => $investor->heard_about_us,
                'location' => $property->location,
                'property_type' => $property->type,
            ]);

            // Held, not sold. A reserved unit leaves the catalogue so two
            // people cannot commit to the same flat while one waits.
            $property->forceFill(['status' => 'reserved'])->save();

            return $commitment;
        });

        return [
            'ok' => true,
            'message' => 'Commitment submitted for approval.',
            'commitment' => $commitment,
        ];
    }

    /**
     * An admin approves it. This is where the plan and the commission exist.
     *
     * Everything inside one transaction: the plan, the frozen rate, the payout
     * accrual and the unit going to sold. A half-approved commitment — a plan
     * with no commission, or a sold unit with no schedule — is worse than a
     * refused one, because nothing downstream can tell that it is broken.
     */
    public function approve(Deal $commitment, User $admin): array
    {
        if ($blocker = $commitment->approvalBlocker()) {
            return $this->no($blocker);
        }

        $property = $commitment->property;
        $months = (int) $commitment->duration_months;

        // Read the rate ONCE, here, and never again.
        $rate = Property::normaliseRate($property->commission_rate);
        $commission = (float) $commitment->value * $rate;

        DB::transaction(function () use ($commitment, $property, $months, $rate, $commission, $admin) {
            $commitment->forceFill([
                'status' => 'active',
                'commission_rate' => $rate,
                'commission' => $commission,
                'approved_at' => now(),
                'approved_by' => $admin->id,
                // Credit is pinned now. If the investor is later moved to a
                // different realtor, this sale stays with whoever earned it.
                'agent_id' => $commitment->customer->agent_id,
            ])->save();

            $this->buildPlan($commitment, $months);

            $property->forceFill([
                'status' => 'sold',
                'sold_at' => now(),
                'days_on_market' => (int) $property->listed_at->diffInDays(now()),
            ])->save();

            // Accrued, not paid. Finance pays it when Finance pays it.
            CommissionPayout::updateOrCreate(
                ['tenant_id' => $commitment->tenant_id, 'deal_id' => $commitment->id],
                [
                    'agent_id' => $commitment->agent_id,
                    'amount' => $commission,
                    'rate' => $rate,
                    'status' => 'owed',
                ],
            );
        });

        return [
            'ok' => true,
            'commitment' => $commitment->fresh(),
            'message' => $months.' instalments scheduled · NGN '
                .number_format($commission).' commission accrued',
        ];
    }

    /**
     * Cancel a commitment that was already approved.
     *
     * Different from declining, which only ever applies to something pending
     * and nobody has been paid for. Here a plan exists, instalments may have
     * been received, and commission has been accrued — possibly paid.
     *
     * The accrual is written off rather than deleted. Deleting it would make
     * the realtor's statement silently disagree with what they were told last
     * month, and a statement that rewrites its own history is one nobody can
     * check. If the money already left, that is a real debt and the row says so
     * rather than pretending it never happened.
     */
    public function cancel(Deal $commitment, User $admin, ?string $reason = null): array
    {
        if (! $commitment->isLive()) {
            return $this->no('Only a live commitment can be cancelled.');
        }

        $paid = $commitment->paidAmount();
        $payout = $commitment->payout;
        $clawback = 0.0;

        DB::transaction(function () use ($commitment, $admin, $reason, $payout, &$clawback) {
            $commitment->forceFill([
                'status' => 'cancelled',
                'won' => false,
                'declined_reason' => $reason,
                'closed_at' => now(),
            ])->save();

            // Unpaid instalments stop existing; paid ones stay, because they
            // are a record of money that actually changed hands.
            Payment::where('deal_id', $commitment->id)->whereNull('paid_at')->delete();

            if ($payout) {
                $clawback = $payout->isPaid() ? (float) $payout->amount : 0.0;

                $payout->forceFill([
                    'status' => 'written_off',
                    'recorded_by' => $admin->id,
                ])->save();
            }

            // Back on the market — an unsold unit is stock, not history.
            $commitment->property?->forceFill([
                'status' => 'available',
                'sold_at' => null,
            ])->save();
        });

        return [
            'ok' => true,
            'clawback' => $clawback,
            'message' => $clawback > 0
                ? 'Cancelled. NGN '.number_format($clawback)
                    .' of commission was already paid and is now owed back.'
                : 'Cancelled. The unit is back on the market and the commission written off.',
            'detail' => $paid > 0
                ? 'NGN '.number_format($paid).' already received is kept on the record.'
                : null,
        ];
    }

    public function decline(Deal $commitment, User $admin, ?string $reason = null): array
    {
        if (! $commitment->isPending()) {
            return $this->no('This commitment has already been decided.');
        }

        DB::transaction(function () use ($commitment, $admin, $reason) {
            $commitment->forceFill([
                'status' => 'declined',
                'declined_reason' => $reason,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ])->save();

            // The hold is released — the unit goes back on the market.
            $commitment->property?->forceFill(['status' => 'available'])->save();
        });

        return ['ok' => true, 'message' => 'Commitment declined and the unit released.'];
    }

    /**
     * Generate the instalment schedule.
     *
     * The final instalment carries the rounding remainder rather than spreading
     * it. Splitting 45,000,000 over 7 months leaves kobo somewhere; putting it
     * anywhere but the last row means the schedule does not sum to the price,
     * and a buyer who adds up their own payments finds the product wrong.
     */
    private function buildPlan(Deal $commitment, int $months): void
    {
        $total = (float) $commitment->value;
        $each = floor($total / $months * 100) / 100;
        $rows = [];

        for ($i = 1; $i <= $months; $i++) {
            $amount = $i === $months
                ? round($total - ($each * ($months - 1)), 2)
                : $each;

            $rows[] = [
                'tenant_id' => $commitment->tenant_id,
                'deal_id' => $commitment->id,
                'customer_id' => $commitment->customer_id,
                'agent_id' => $commitment->agent_id,
                'amount' => $amount,
                'instalment' => $i,
                'of_total' => $months,
                'due_at' => now()->addMonthsNoOverflow($i)->startOfDay(),
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Payment::insert($rows);
    }

    private function no(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
