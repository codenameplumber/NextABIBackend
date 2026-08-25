<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\SupportTicket;
use App\Support\Purchase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The buyer's side.
 *
 * The claim being tested is that the portal and the console are two renderings
 * of one set of rows, and that everything a buyer is shown about timing is
 * derived from the clock rather than read from a stored snapshot.
 */
class PortalTest extends TestCase
{
    public function test_payment_status_is_derived_from_the_clock_not_from_the_stored_column(): void
    {
        $payment = Payment::whereNull('paid_at')->first();
        $this->assertNotNull($payment);

        // Force a stale snapshot: the stored column says one thing, the clock
        // says another. The clock has to win.
        $payment->forceFill([
            'status' => 'scheduled',
            'due_at' => now()->subDays(45),
            'paid_at' => null,
        ])->save();

        $this->assertSame('overdue', $payment->fresh()->liveStatus());
        $this->assertGreaterThanOrEqual(44, $payment->fresh()->lateBy());

        $payment->forceFill(['due_at' => now()->addDays(10)])->save();
        $this->assertSame('scheduled', $payment->fresh()->liveStatus());
        $this->assertSame(0, $payment->fresh()->lateBy());
    }

    public function test_a_paid_instalment_is_never_reported_as_late(): void
    {
        $payment = Payment::whereNotNull('paid_at')->first();
        $this->assertNotNull($payment);

        $payment->forceFill(['due_at' => now()->subYear()])->save();

        $this->assertSame('paid', $payment->fresh()->liveStatus());
        $this->assertSame(0, $payment->fresh()->lateBy());
    }

    public function test_next_is_the_earliest_unpaid_instalment(): void
    {
        $customer = $this->client()->customer;
        $purchase = Purchase::for($customer);

        $next = $purchase->next();

        if (! $next) {
            $this->markTestSkipped('This buyer is fully settled.');
        }

        // The bug this guards: bucketing overdue-then-upcoming skips anything
        // past due but under 30 days, and the portal announces instalment 5 as
        // next while 4 sits unpaid.
        $earlierUnpaid = $purchase->payments
            ->filter(fn ($p) => $p->liveStatus() !== 'paid' && $p->due_at->lt($next->due_at));

        $this->assertTrue($earlierUnpaid->isEmpty(), 'An earlier unpaid instalment was skipped.');
    }

    public function test_the_portal_and_the_console_agree_on_what_is_owed(): void
    {
        $customer = $this->client()->customer;
        $purchase = Purchase::for($customer);

        $consoleView = Payment::where('customer_id', $customer->id)
            ->whereNull('paid_at')->sum('amount');

        // Only instalments on the deal the portal is showing.
        $portalView = $purchase->outstanding();

        $this->assertGreaterThan(0, $consoleView);
        $this->assertLessThanOrEqual(round((float) $consoleView, 2), round($portalView, 2));
    }

    public function test_all_four_portal_pages_render(): void
    {
        foreach (['/portal', '/portal/payments', '/portal/documents', '/portal/support'] as $url) {
            $this->actingAs($this->client())->get($url)->assertOk();
        }
    }

    public function test_raising_a_ticket_writes_a_row_the_agency_can_see(): void
    {
        $client = $this->client();
        $before = SupportTicket::count();

        Livewire::actingAs($client)
            ->test(\App\Livewire\Portal\Support::class)
            ->set('topic', 'payment-plan')
            ->set('body', 'The next instalment lands the same week as my rent. Can we move it?')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertSame($before + 1, SupportTicket::count());

        $ticket = SupportTicket::latest('id')->first();
        $this->assertSame($client->customer_id, $ticket->customer_id);
        $this->assertSame($this->tenant->id, $ticket->tenant_id);
        $this->assertSame('open', $ticket->status);
    }

    public function test_a_ticket_needs_enough_detail_to_act_on(): void
    {
        Livewire::actingAs($this->client())
            ->test(\App\Livewire\Portal\Support::class)
            ->set('body', 'broken')
            ->call('send')
            ->assertHasErrors(['body']);
    }

    public function test_title_documents_wait_on_settlement(): void
    {
        $response = $this->actingAs($this->client())->get('/portal/documents');

        $purchase = Purchase::for($this->client()->customer);

        if ($purchase->settled()) {
            $this->markTestSkipped('This buyer has settled — nothing should be blocked.');
        }

        // The deed genuinely does wait on the final instalment, and the page
        // says which. It does not quietly show it as available.
        $response->assertSee('Deed of assignment');
        $response->assertSee('outstanding', escape: false);
    }
}
