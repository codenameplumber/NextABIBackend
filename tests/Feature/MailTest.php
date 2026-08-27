<?php

namespace Tests\Feature;

use App\Engine\ActionBus;
use App\Mail\AgencyWelcome;
use App\Mail\PaymentReminder;
use App\Mail\SupportTicketRaised;
use App\Mail\SupportTicketReceived;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Email.
 *
 * The rule being tested is that an action which says it sent something did.
 * "19 reminders issued" while no buyer heard from anyone is worse than not
 * having the button, because it makes the whole action log unreliable.
 */
class MailTest extends TestCase
{
    /** The fields a registration now carries, minus the referral code. */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ifeoma Nwachukwu',
            'email' => 'ifeoma@harbour.test',
            'phone' => '08031234567',
            'state' => 'Lagos',
            'lga' => 'Eti-Osa',
            'income_bracket' => '10m-25m',
            'occupation' => 'business-owner',
            'heard_about_us' => 'instagram',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ], $overrides);
    }

    public function test_a_new_investor_gets_a_welcome(): void
    {
        Mail::fake();

        $this->post('/register', $this->registration())
            ->assertRedirect(route('portal.browse'));

        Mail::assertSent(\App\Mail\InvestorWelcome::class,
            fn ($m) => $m->hasTo('ifeoma@harbour.test'));
    }

    public function test_a_registration_through_a_realtors_link_names_them(): void
    {
        Mail::fake();

        $realtor = \App\Models\Agent::query()->first();
        $realtor->forceFill(['status' => 'approved', 'code' => 'WELC01'])->save();

        $this->post('/register', $this->registration(['ref' => 'WELC01']))
            ->assertRedirect(route('portal.browse'));

        // The welcome says who is looking after them. An investor who
        // registered through a link already believes somebody is.
        Mail::assertSent(\App\Mail\InvestorWelcome::class,
            fn ($m) => $m->realtor?->id === $realtor->id);

        $this->assertSame(
            $realtor->id,
            \App\Models\Customer::where('email', 'ifeoma@harbour.test')->first()?->agent_id,
        );
    }

    public function test_an_unknown_code_still_registers_them_unassigned(): void
    {
        Mail::fake();

        // Somebody mistyping a code they were given must not be blocked at the
        // last step of a form — they land in the unassigned queue instead.
        $this->post('/register', $this->registration(['ref' => 'NOTREAL']))
            ->assertRedirect(route('portal.browse'));

        $investor = \App\Models\Customer::where('email', 'ifeoma@harbour.test')->first();

        $this->assertNotNull($investor);
        $this->assertNull($investor->agent_id, 'They belong in the unassigned queue.');
    }

    public function test_an_approved_realtor_is_emailed_their_code(): void
    {
        Mail::fake();

        $realtor = \App\Models\Agent::query()->skip(3)->first();
        $realtor->forceFill(['status' => 'pending', 'code' => null])->save();

        app(\App\Platform\Realtors::class)->approve($realtor, $this->staff());

        // The code and the link are the whole reason they have been waiting,
        // so the email carries them rather than saying "sign in to find out".
        Mail::assertSent(\App\Mail\RealtorApproved::class, function ($m) use ($realtor) {
            return $m->hasTo($realtor->email) && filled($m->realtor->code);
        });
    }

    public function test_raising_a_ticket_emails_both_sides(): void
    {
        Mail::fake();

        Livewire::actingAs($this->client())
            ->test(\App\Livewire\Portal\Support::class)
            ->set('topic', 'documents')
            ->set('body', 'When should I expect the survey plan for my unit?')
            ->call('send')
            ->assertHasNoErrors();

        $ticket = SupportTicket::latest('id')->first();

        Mail::assertSent(SupportTicketRaised::class, fn ($m) => $m->hasTo($ticket->agent->email));
        Mail::assertSent(SupportTicketReceived::class, fn ($m) => $m->hasTo($ticket->customer->email));
    }

    public function test_the_agent_can_reply_straight_to_the_buyer(): void
    {
        $ticket = SupportTicket::with(['customer', 'agent'])->first()
            ?? $this->makeTicket();

        $envelope = (new SupportTicketRaised($ticket))->envelope();

        $this->assertSame(
            $ticket->customer->email,
            $envelope->replyTo[0]->address ?? null,
            'Hitting reply would go to a no-reply address instead of the customer.',
        );
    }

    public function test_send_reminders_actually_sends_them(): void
    {
        Mail::fake();

        $result = ActionBus::for($this->tenant)->manual(
            type: 'SEND_REMINDERS',
            payload: ['filter' => 'overdue'],
            label: 'Chase overdue',
            key: 'test:reminders:'.uniqid(),
        );

        $this->assertTrue($result['ok'], $result['message']);

        // The action's own detail line reports how many left, so the log and
        // the mailbox cannot disagree.
        $this->assertStringContainsString('sent', $result['detail']);

        // Queued, not sent: a sweep of hundreds must not block the click that
        // started it. With QUEUE_CONNECTION=sync this still leaves immediately.
        Mail::assertQueued(PaymentReminder::class);
        Mail::assertNotSent(PaymentReminder::class);
    }

    public function test_a_reminder_is_one_email_per_customer_not_per_instalment(): void
    {
        Mail::fake();

        ActionBus::for($this->tenant)->manual(
            type: 'SEND_REMINDERS',
            payload: ['filter' => 'overdue'],
            label: 'Chase overdue',
            key: 'test:grouping:'.uniqid(),
        );

        $recipients = [];

        Mail::assertQueued(PaymentReminder::class, function ($mail) use (&$recipients) {
            $recipients[] = $mail->customer->id;

            return true;
        });

        $this->assertSame(
            count($recipients),
            count(array_unique($recipients)),
            'A customer with three late instalments got three emails.',
        );
    }

    public function test_a_bad_address_does_not_roll_back_the_sweep(): void
    {
        // The action's result is measured from the data, not from whether the
        // mail server was reachable.
        Customer::query()->limit(3)->update(['email' => null]);

        $result = ActionBus::for($this->tenant)->manual(
            type: 'SEND_REMINDERS',
            payload: ['filter' => 'overdue'],
            label: 'Chase overdue',
            key: 'test:badaddr:'.uniqid(),
        );

        $this->assertTrue($result['ok'], $result['message']);
    }

    public function test_the_paths_a_user_waits_on_still_send_inline(): void
    {
        Mail::fake();

        // A ticket is one message on a click. Queueing it would mean the buyer
        // is told it was sent before anything left, and the agent's inbox lags
        // behind the portal.
        Livewire::actingAs($this->client())
            ->test(\App\Livewire\Portal\Support::class)
            ->set('body', 'Checking that this reaches my agent straight away.')
            ->call('send');

        Mail::assertSent(SupportTicketRaised::class);
        Mail::assertSent(SupportTicketReceived::class);
    }

    public function test_a_reset_link_is_branded_and_works(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'ada@anchorstone.ng'])
            ->assertSessionHas('status');

        Notification::assertSentTo($this->staff(), ResetPasswordLink::class);
    }

    public function test_the_reset_page_never_says_whether_an_account_exists(): void
    {
        // Enumerating who banks with which agency is a real leak for a product
        // holding whole client books.
        $known = $this->post('/forgot-password', ['email' => 'ada@anchorstone.ng']);
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@nowhere.test']);

        $this->assertSame(
            $known->getSession()->get('status'),
            $unknown->getSession()->get('status'),
            'The two answers differ, which is an account-enumeration oracle.',
        );
    }

    public function test_a_password_can_actually_be_reset(): void
    {
        $user = $this->staff();
        $token = \Illuminate\Support\Facades\Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('a-brand-new-password', $user->fresh()->password),
        );
    }

    public function test_the_digest_only_sends_when_there_is_something_to_say(): void
    {
        Mail::fake();
        \App\Engine\InsightEngine::for($this->tenant)->run();

        // Nothing flagged: no mail, and no pretending otherwise. A digest that
        // arrives every morning regardless is a digest people filter away.
        \App\Models\Insight::query()->update(['dismissed_at' => now()]);
        $this->artisan('nexabi:digest')->assertSuccessful();
        Mail::assertNothingQueued();

        \App\Models\Insight::query()->update(['dismissed_at' => null]);
        $this->artisan('nexabi:digest')->assertSuccessful();
        Mail::assertQueued(\App\Mail\FindingsDigest::class);
    }

    public function test_the_digest_is_ranked_for_each_recipients_seat(): void
    {
        Mail::fake();
        \App\Engine\InsightEngine::for($this->tenant)->run();
        $this->artisan('nexabi:digest --force')->assertSuccessful();

        $orders = [];

        Mail::assertQueued(\App\Mail\FindingsDigest::class, function ($mail) use (&$orders) {
            $orders[$mail->user->role] = collect($mail->insights)->pluck('id')->all();

            return true;
        });

        if (count($orders) < 2) {
            $this->markTestSkipped('Only one staff seat in this fixture.');
        }

        $this->assertNotSame(
            array_values($orders)[0],
            array_values($orders)[1],
            'Two different seats received an identical ordering.',
        );
    }

    private function makeTicket(): SupportTicket
    {
        return SupportTicket::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => Customer::value('id'),
            'agent_id' => \App\Models\Agent::value('id'),
            'reference' => 'TCK-TEST', 'topic' => 'Documents and title',
            'body' => 'Test.', 'status' => 'open',
        ])->load(['customer', 'agent']);
    }
}
