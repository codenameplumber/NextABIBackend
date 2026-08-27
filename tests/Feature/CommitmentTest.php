<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Property;
use App\Models\User;
use App\Platform\Commitments;
use App\Platform\Instalments;
use App\Platform\Realtors;
use Tests\TestCase;

/**
 * The commercial core: committing, approving, and the two rules that govern
 * money once it has been earned.
 *
 * The rule worth reading twice is the frozen commission rate. A payment's
 * status must always be derived live, because a stored one goes stale the
 * moment the clock moves. A commission rate is the exact opposite: it is
 * contractual, so it must be captured once and never re-read. Getting those two
 * the wrong way round is the kind of error nobody notices until an audit.
 */
class CommitmentTest extends TestCase
{
    private function admin(): User
    {
        return User::where('type', 'admin')->firstOrFail();
    }

    private function approvedRealtor(): Agent
    {
        $realtor = Agent::query()->first();

        $realtor->forceFill([
            'status' => 'approved',
            'code' => $realtor->code ?: Agent::generateCode($realtor->name),
        ])->save();

        return $realtor->fresh();
    }

    /** A property nobody has committed to, priced so the maths is checkable. */
    private function freshProperty(float $rate = 0.05): Property
    {
        return Property::create([
            'tenant_id' => $this->tenant->id,
            'ref' => 'TEST-'.uniqid(),
            'title' => 'Test Court, Lekki',
            'type' => 'Apartment',
            'location' => 'Lekki',
            'bedrooms' => 3,
            'size_sqm' => 180,
            'price' => 48_000_000,
            'status' => 'available',
            'listed' => true,
            'listed_at' => now()->subDays(30),
            'commission_rate' => $rate,
            'min_duration_months' => 3,
            'max_duration_months' => 24,
        ]);
    }

    private function investor(bool $assigned = true): Customer
    {
        return Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Adaeze Test '.uniqid(),
            'email' => 'adaeze'.uniqid().'@example.com',
            'agent_id' => $assigned ? $this->approvedRealtor()->id : null,
            'assigned_at' => $assigned ? now() : null,
            'since' => now(),
            'state' => 'Lagos',
            'lga' => 'Eti-Osa',
            'heard_about_us' => 'instagram',
        ]);
    }

    /* ------------------------------------------------------------ committing */

    public function test_committing_holds_the_unit_rather_than_selling_it(): void
    {
        $property = $this->freshProperty();

        $result = (new Commitments)->open($this->investor(), $property, 12);

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame('pending', $result['commitment']->status);

        // Held, not sold. Two people must not be able to commit to one flat.
        $this->assertSame('reserved', $property->fresh()->status);

        // No plan exists yet — approval is what creates one.
        $this->assertSame(0, $result['commitment']->payments()->count());
    }

    public function test_a_duration_the_property_does_not_offer_is_refused(): void
    {
        $property = $this->freshProperty();
        $property->forceFill(['min_duration_months' => 6, 'max_duration_months' => 12])->save();

        $result = (new Commitments)->open($this->investor(), $property, 36);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not offered', $result['message']);
        $this->assertSame('available', $property->fresh()->status);
    }

    public function test_the_same_investor_cannot_commit_twice_to_one_property(): void
    {
        $property = $this->freshProperty();
        $investor = $this->investor();

        $this->assertTrue((new Commitments)->open($investor, $property, 12)['ok']);

        // The unit is reserved now, so the second attempt is refused on
        // availability before it ever reaches the duplicate check.
        $second = (new Commitments)->open($investor, $property, 12);
        $this->assertFalse($second['ok']);
    }

    /* ------------------------------------------------------------- approving */

    public function test_a_commitment_cannot_be_approved_without_a_realtor(): void
    {
        $property = $this->freshProperty();
        $investor = $this->investor(assigned: false);

        $commitment = (new Commitments)->open($investor, $property, 12)['commitment'];

        $result = (new Commitments)->approve($commitment, $this->admin());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('realtor', $result['message']);

        // Nothing may have happened: no plan, no payout, still pending.
        $this->assertSame('pending', $commitment->fresh()->status);
        $this->assertSame(0, $commitment->payments()->count());
        $this->assertSame(0, CommissionPayout::where('deal_id', $commitment->id)->count());
    }

    public function test_approval_generates_the_plan_and_accrues_commission(): void
    {
        $property = $this->freshProperty(rate: 0.05);
        $commitment = (new Commitments)->open($this->investor(), $property, 12)['commitment'];

        $result = (new Commitments)->approve($commitment, $this->admin());
        $this->assertTrue($result['ok'], $result['message']);

        $commitment = $commitment->fresh();

        $this->assertSame('active', $commitment->status);
        $this->assertSame(12, $commitment->payments()->count());
        $this->assertSame('sold', $property->fresh()->status);

        // 5% of 48,000,000.
        $this->assertEqualsWithDelta(2_400_000, $commitment->commission, 0.01);

        $payout = CommissionPayout::where('deal_id', $commitment->id)->firstOrFail();
        $this->assertSame('owed', $payout->status);
        $this->assertEqualsWithDelta(2_400_000, $payout->amount, 0.01);
    }

    public function test_the_schedule_sums_to_the_price_exactly(): void
    {
        // Seven months into 48,000,000 does not divide evenly, which is the
        // case that exposes a schedule built with naive rounding.
        $property = $this->freshProperty();
        $property->forceFill(['min_duration_months' => 1, 'max_duration_months' => 24])->save();

        $commitment = (new Commitments)->open($this->investor(), $property, 9)['commitment'];
        (new Commitments)->approve($commitment, $this->admin());

        $total = (float) $commitment->fresh()->payments()->sum('amount');

        $this->assertEqualsWithDelta(48_000_000, $total, 0.01,
            'The instalments must add up to the price — a buyer will check.');
    }

    public function test_declining_releases_the_unit(): void
    {
        $property = $this->freshProperty();
        $commitment = (new Commitments)->open($this->investor(), $property, 12)['commitment'];

        $result = (new Commitments)->decline($commitment, $this->admin(), 'Failed verification');

        $this->assertTrue($result['ok']);
        $this->assertSame('declined', $commitment->fresh()->status);
        $this->assertSame('available', $property->fresh()->status);
    }

    /* ------------------------------------------------------- the frozen rate */

    public function test_the_commission_rate_is_frozen_at_approval(): void
    {
        $property = $this->freshProperty(rate: 0.10);
        $commitment = (new Commitments)->open($this->investor(), $property, 12)['commitment'];

        (new Commitments)->approve($commitment, $this->admin());
        $earned = $commitment->fresh()->commission;

        // The agency changes its mind about this property next quarter.
        $property->forceFill(['commission_rate' => 0.02])->save();

        $this->assertEqualsWithDelta($earned, $commitment->fresh()->commissionDue(), 0.01,
            'Editing a listing must never rewrite commission already earned.');
        $this->assertEqualsWithDelta(0.10, $commitment->fresh()->commission_rate, 0.0001);
    }

    public function test_commission_above_fifteen_percent_is_impossible(): void
    {
        $this->assertEqualsWithDelta(0.15, Property::normaliseRate(0.40), 0.0001);
        $this->assertEqualsWithDelta(0.15, Property::normaliseRate(40), 0.0001);

        // A percentage typed into a form and a rate held in the database are
        // the same intention written two ways.
        $this->assertEqualsWithDelta(0.12, Property::normaliseRate(12), 0.0001);
        $this->assertEqualsWithDelta(0.12, Property::normaliseRate(0.12), 0.0001);
    }

    /* ---------------------------------------------------------- the journey */

    public function test_recording_every_instalment_settles_the_commitment(): void
    {
        $property = $this->freshProperty();
        $property->forceFill(['min_duration_months' => 1])->save();

        $commitment = (new Commitments)->open($this->investor(), $property, 3)['commitment'];
        (new Commitments)->approve($commitment, $this->admin());

        $service = new Instalments;
        $admin = $this->admin();
        $results = [];

        foreach ($commitment->fresh()->instalments as $instalment) {
            $results[] = $service->record($instalment, $admin, 'TRF-'.$instalment->instalment);
        }

        // Feedback is asked at exactly two moments, and this is both of them.
        $this->assertSame('journey_started', $results[0]['feedbackDue']);
        $this->assertNull($results[1]['feedbackDue']);
        $this->assertSame('completed', $results[2]['feedbackDue']);

        $commitment = $commitment->fresh();
        $this->assertSame('completed', $commitment->status);
        $this->assertTrue($commitment->won);
        $this->assertEqualsWithDelta(1.0, $commitment->progress(), 0.001);
        $this->assertEqualsWithDelta(0.0, $commitment->outstanding(), 0.01);
    }

    public function test_the_same_transfer_cannot_be_recorded_twice(): void
    {
        $property = $this->freshProperty();
        $commitment = (new Commitments)->open($this->investor(), $property, 12)['commitment'];
        (new Commitments)->approve($commitment, $this->admin());

        $instalment = $commitment->fresh()->instalments->first();
        $service = new Instalments;

        $this->assertTrue($service->record($instalment, $this->admin(), 'TRF-1')['ok']);

        $again = $service->record($instalment->fresh(), $this->admin(), 'TRF-1');
        $this->assertFalse($again['ok'], 'Keying one transfer twice would overstate collections.');
    }

    public function test_reversing_the_final_payment_reopens_the_commitment(): void
    {
        $property = $this->freshProperty();
        $property->forceFill(['min_duration_months' => 1])->save();

        $commitment = (new Commitments)->open($this->investor(), $property, 3)['commitment'];
        (new Commitments)->approve($commitment, $this->admin());

        $service = new Instalments;
        foreach ($commitment->fresh()->instalments as $instalment) {
            $service->record($instalment, $this->admin(), 'TRF');
        }

        $this->assertSame('completed', $commitment->fresh()->status);

        $last = $commitment->fresh()->instalments->last();
        $this->assertTrue($service->reverse($last, $this->admin())['ok']);

        $this->assertSame('active', $commitment->fresh()->status,
            'A reversal that leaves the parent settled is a lie.');
    }

    /* ------------------------------------------------------------ cancelling */

    public function test_cancelling_releases_the_unit_and_writes_off_the_commission(): void
    {
        $property = $this->freshProperty();
        $commitment = (new Commitments)->open($this->investor(), $property, 12)['commitment'];
        (new Commitments)->approve($commitment, $this->admin());

        $result = (new Commitments)->cancel($commitment->fresh(), $this->admin(), 'Buyer withdrew');

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame('cancelled', $commitment->fresh()->status);
        $this->assertSame('available', $property->fresh()->status);

        // Written off, not deleted. A statement that rewrites its own history
        // between one month and the next is one nobody can check.
        $payout = CommissionPayout::where('deal_id', $commitment->id)->firstOrFail();
        $this->assertSame('written_off', $payout->status);
    }

    public function test_cancelling_keeps_money_already_received(): void
    {
        $property = $this->freshProperty();
        $property->forceFill(['min_duration_months' => 1])->save();

        $commitment = (new Commitments)->open($this->investor(), $property, 6)['commitment'];
        (new Commitments)->approve($commitment, $this->admin());

        $first = $commitment->fresh()->instalments->first();
        (new Instalments)->record($first, $this->admin(), 'TRF-KEEP');

        (new Commitments)->cancel($commitment->fresh(), $this->admin());

        $rows = $commitment->fresh()->payments()->get();

        // The paid one survives — it is a record of money that changed hands.
        // The unpaid ones do not, because nobody owes them any more.
        $this->assertCount(1, $rows);
        $this->assertNotNull($rows->first()->paid_at);
    }

    public function test_cancelling_after_commission_was_paid_reports_the_debt(): void
    {
        $property = $this->freshProperty(rate: 0.05);
        $commitment = (new Commitments)->open($this->investor(), $property, 12)['commitment'];
        (new Commitments)->approve($commitment, $this->admin());

        // Finance settles with the realtor, then the sale falls through.
        CommissionPayout::where('deal_id', $commitment->id)
            ->update(['status' => 'paid', 'paid_at' => now()]);

        $result = (new Commitments)->cancel($commitment->fresh(), $this->admin());

        $this->assertEqualsWithDelta(2_400_000, $result['clawback'], 0.01);
        $this->assertStringContainsString('owed back', $result['message']);
    }

    public function test_a_pending_commitment_cannot_be_cancelled(): void
    {
        // Declining is the operation for something nobody has been paid for.
        $property = $this->freshProperty();
        $commitment = (new Commitments)->open($this->investor(), $property, 12)['commitment'];

        $result = (new Commitments)->cancel($commitment, $this->admin());

        $this->assertFalse($result['ok']);
        $this->assertSame('pending', $commitment->fresh()->status);
    }

    /* ------------------------------------------------------------ portfolio */

    public function test_an_investor_holding_two_properties_can_see_both(): void
    {
        $investor = $this->investor();

        foreach ([$this->freshProperty(), $this->freshProperty()] as $property) {
            $commitment = (new Commitments)->open($investor, $property, 12)['commitment'];
            (new Commitments)->approve($commitment, $this->admin());
        }

        // Browsing encourages a second purchase, so a portal that shows one and
        // hides the rest is quietly wrong for anyone who took it up.
        $portfolio = \App\Support\Purchase::portfolio($investor->fresh());

        $this->assertCount(2, $portfolio);
        $this->assertCount(2, $portfolio->pluck('deal.id')->unique());

        foreach ($portfolio as $purchase) {
            $this->assertSame(12, $purchase->payments->count());
        }
    }

    public function test_a_pending_commitment_is_listed_separately_from_a_live_one(): void
    {
        $investor = $this->investor();

        $live = (new Commitments)->open($investor, $this->freshProperty(), 12)['commitment'];
        (new Commitments)->approve($live, $this->admin());

        (new Commitments)->open($investor, $this->freshProperty(), 12);

        // A pending commitment has no plan, so putting it in the portfolio
        // would show a purchase with an empty schedule.
        $this->assertCount(1, \App\Support\Purchase::portfolio($investor->fresh()));
        $this->assertCount(1, \App\Support\Purchase::awaiting($investor->fresh()));
    }

    /* ------------------------------------------------------------- the code */

    public function test_a_referral_code_only_resolves_once_approved(): void
    {
        $service = new Realtors;

        $realtor = Agent::query()->skip(1)->first();
        $realtor->forceFill(['status' => 'pending', 'code' => 'ZZTEST9'])->save();

        $this->assertNull($service->findByCode('ZZTEST9'),
            'An unapproved code must not sign anybody up.');

        $realtor->forceFill(['status' => 'approved'])->save();

        // Dictated aloud and typed from memory — the lookup forgives case and
        // spacing, and nothing else.
        $this->assertNotNull($service->findByCode('zztest9'));
        $this->assertNotNull($service->findByCode('  ZZTEST9  '));
        $this->assertNull($service->findByCode('NOPE00'));
        $this->assertNull($service->findByCode(null));
    }

    public function test_generated_codes_avoid_characters_people_mishear(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $code = Agent::generateCode('Chidi Okafor');

            $this->assertSame(6, strlen($code));
            $this->assertSame('CO', substr($code, 0, 2));
            $this->assertDoesNotMatchRegularExpression('/[0158BILOS]/', substr($code, 2),
                'These are the characters that get misheard down a phone line.');
        }
    }
}
