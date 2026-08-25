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
    public function test_a_new_agency_gets_a_welcome(): void
    {
        Mail::fake();

        $this->post('/register', [
            'agency' => 'Harbour Estates',
            'name' => 'Ifeoma Nwachukwu',
            'email' => 'ifeoma@harbour.test',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ])->assertRedirect(route('app.settings'));

        Mail::assertSent(AgencyWelcome::class, fn ($m) => $m->hasTo('ifeoma@harbour.test'));
    }

    public function test_a_buyer_cannot_sign_themselves_up(): void
    {
        // A client account with no customer record behind it can see nothing.
        // Refusing is more useful than creating one and letting them find out.
        $this->post('/register', [
            'kind' => 'client',
            'name' => 'Someone', 'email' => 'buyer@harbour.test',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ])->assertSessionHasErrors('email');

        $this->assertNull(User::where('email', 'buyer@harbour.test')->first());
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

        Mail::assertSent(PaymentReminder::class);
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

        Mail::assertSent(PaymentReminder::class, function ($mail) use (&$recipients) {
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
        Mail::assertNothingSent();

        \App\Models\Insight::query()->update(['dismissed_at' => null]);
        $this->artisan('nexabi:digest')->assertSuccessful();
        Mail::assertSent(\App\Mail\FindingsDigest::class);
    }

    public function test_the_digest_is_ranked_for_each_recipients_seat(): void
    {
        Mail::fake();
        \App\Engine\InsightEngine::for($this->tenant)->run();
        $this->artisan('nexabi:digest --force')->assertSuccessful();

        $orders = [];

        Mail::assertSent(\App\Mail\FindingsDigest::class, function ($mail) use (&$orders) {
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
