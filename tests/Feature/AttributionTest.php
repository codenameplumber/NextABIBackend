<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Platform\Commitments;
use App\Platform\Realtors;
use App\Platform\Registrations;
use App\Support\Nigeria;
use Tests\TestCase;

/**
 * Attribution, from an advert to a paid commission.
 *
 * The spine of the commercial model. Two axes answer two different questions
 * and neither substitutes for the other: the realtor CODE says who brought
 * somebody in and drives commission; HEARD ABOUT US says which channel reached
 * them and drives marketing. A buyer can hear a radio advert and still be
 * signed up by a realtor, so a system that collapses them into one field
 * cannot answer either question honestly.
 */
class AttributionTest extends TestCase
{
    private function approvedRealtor(int $skip = 0): Agent
    {
        $realtor = Agent::query()->skip($skip)->first();

        $realtor->forceFill([
            'status' => 'approved',
            'code' => 'ATTR'.$skip.'X',
        ])->save();

        return $realtor->fresh();
    }

    private function profile(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Chidinma Test',
            'email' => 'chidinma'.uniqid().'@example.com',
            'phone' => '08030000000',
            'state' => 'Lagos',
            'lga' => 'Ikeja',
            'income_bracket' => '10m-25m',
            'occupation' => 'engineering',
            'heard_about_us' => 'radio',
            'password' => 'a-long-enough-password',
        ], $overrides);
    }

    /* ------------------------------------------------------------- the two axes */

    public function test_the_code_and_the_channel_are_recorded_separately(): void
    {
        $realtor = $this->approvedRealtor();

        $result = app(Registrations::class)->investor(
            $this->tenant,
            $this->profile(['heard_about_us' => 'radio']),
            $realtor->code,
        );

        $investor = $result['user']->customer;

        // Who brought them, and how they heard of us, are different facts.
        $this->assertSame($realtor->id, $investor->agent_id);
        $this->assertSame('radio', $investor->heard_about_us);
    }

    public function test_registering_direct_leaves_them_in_the_unassigned_queue(): void
    {
        $before = Customer::query()->unassigned()->count();

        $result = app(Registrations::class)->investor($this->tenant, $this->profile());

        $this->assertNull($result['user']->customer->agent_id);
        $this->assertSame($before + 1, Customer::query()->unassigned()->count());
    }

    public function test_a_mismatched_state_and_lga_is_refused(): void
    {
        // Both halves have to agree, or the field is decoration.
        $result = app(Registrations::class)->investor(
            $this->tenant,
            $this->profile(['state' => 'Lagos', 'lga' => 'Bwari']),
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('local government', $result['message']);
    }

    /* --------------------------------------------------------- lead to investor */

    public function test_a_questionnaire_capture_lands_with_the_realtor_who_sent_it(): void
    {
        $realtor = $this->approvedRealtor();

        $result = app(Registrations::class)->lead(
            $this->tenant,
            [
                'name' => 'Ifeanyi Enquiry',
                'phone' => '08039999999',
                'email' => 'ifeanyi.enquiry@example.com',
                'state' => 'Lagos',
                'lga' => 'Eti-Osa',
                'income_bracket' => '25m-50m',
                'occupation' => 'oil-gas',
                'heard_about_us' => 'instagram',
                'budget' => 90_000_000,
                'wants_location' => 'Lekki',
            ],
            $realtor->code,
        );

        $lead = $result['lead'];

        $this->assertSame($realtor->id, $lead->assigned_agent_id);
        $this->assertSame('instagram', $lead->heard_about_us);
        // A lead is not an account — that is the whole point of the form.
        $this->assertNull(User::where('email', 'ifeanyi.enquiry@example.com')->first());
    }

    public function test_an_enquiry_that_becomes_a_registration_closes_its_own_lead(): void
    {
        $realtor = $this->approvedRealtor();
        $email = 'converts'.uniqid().'@example.com';

        app(Registrations::class)->lead(
            $this->tenant,
            ['name' => 'Will Convert', 'phone' => '0803', 'email' => $email,
                'state' => 'Lagos', 'lga' => 'Ikeja', 'heard_about_us' => 'friend'],
            $realtor->code,
        );

        $result = app(Registrations::class)->investor(
            $this->tenant,
            $this->profile(['email' => $email]),
            $realtor->code,
        );

        $lead = Lead::where('email', $email)->firstOrFail();

        // Left open, the realtor's list shows a ghost row that looks like it
        // went nowhere — when in fact it produced a customer.
        $this->assertFalse($lead->is_open);
        $this->assertSame('won', $lead->status);
        $this->assertSame($result['user']->id, $lead->converted_user_id);
    }

    /* ------------------------------------------------- through to commission */

    public function test_commission_reaches_the_realtor_who_brought_them_in(): void
    {
        $realtor = $this->approvedRealtor();

        $investor = app(Registrations::class)
            ->investor($this->tenant, $this->profile(), $realtor->code)['user']->customer;

        $property = Property::query()->onMarket()->firstOrFail();
        $property->forceFill(['commission_rate' => 0.06])->save();

        $months = $property->durationOptions()[0];
        $commitment = app(Commitments::class)->open($investor, $property, $months)['commitment'];

        app(Commitments::class)->approve($commitment, User::where('type', 'admin')->firstOrFail());

        $payout = CommissionPayout::where('deal_id', $commitment->id)->firstOrFail();

        $this->assertSame($realtor->id, $payout->agent_id);
        $this->assertEqualsWithDelta((float) $property->price * 0.06, $payout->amount, 0.01);
        $this->assertSame('owed', $payout->status);
    }

    public function test_moving_an_investor_later_does_not_move_a_sale_already_credited(): void
    {
        $first = $this->approvedRealtor(0);
        $second = $this->approvedRealtor(1);

        $investor = app(Registrations::class)
            ->investor($this->tenant, $this->profile(), $first->code)['user']->customer;

        $property = Property::query()->onMarket()->skip(2)->firstOrFail();
        $commitment = app(Commitments::class)
            ->open($investor, $property, $property->durationOptions()[0])['commitment'];

        app(Commitments::class)->approve($commitment, User::where('type', 'admin')->firstOrFail());

        // The investor is later handed to somebody else.
        $investor->forceFill(['agent_id' => $second->id])->save();

        // The sale stays with whoever earned it. Credit is pinned at approval.
        $this->assertSame($first->id, $commitment->fresh()->agent_id);
        $this->assertSame(
            $first->id,
            CommissionPayout::where('deal_id', $commitment->id)->first()->agent_id,
        );
    }

    /* -------------------------------------------------------------- the code */

    public function test_a_suspended_realtor_keeps_their_code_and_their_book(): void
    {
        $realtor = $this->approvedRealtor();

        $investor = app(Registrations::class)
            ->investor($this->tenant, $this->profile(), $realtor->code)['user']->customer;

        app(Realtors::class)->suspend($realtor, User::where('type', 'admin')->firstOrFail());

        // Links already on flyers keep resolving to a real person, and their
        // investors keep their owner — suspension is not a reason to orphan a
        // book of business.
        $this->assertNotNull($realtor->fresh()->code);
        $this->assertSame($realtor->id, $investor->fresh()->agent_id);

        // But the code stops signing anybody new up.
        $this->assertNull(app(Realtors::class)->findByCode($realtor->code));
    }

    public function test_every_state_and_lga_offered_at_registration_is_real(): void
    {
        // 36 states plus the FCT, and the full statutory 774 local governments.
        $this->assertCount(37, Nigeria::STATES);
        $this->assertSame(774, Nigeria::lgaCount());

        $this->assertTrue(Nigeria::isLga('Lagos', 'Eti-Osa'));
        $this->assertTrue(Nigeria::isLga('Federal Capital Territory', 'Bwari'));
        $this->assertFalse(Nigeria::isLga('Lagos', 'Bwari'));
        $this->assertFalse(Nigeria::isState('Atlantis'));
    }
}
