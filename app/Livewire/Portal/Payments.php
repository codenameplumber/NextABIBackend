<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ServesTheClient;
use App\Models\Payment;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Client portal — the payment schedule.
 *
 * One hero number (what is still owed), then context, then the small stuff.
 * Five equal-weight statistics in a row made the reader do the ranking
 * themselves, which is work the page should have done for them.
 *
 * Every status on this page is derived from the clock, never read from the
 * stored column. An instalment three weeks past due that still reports itself
 * as scheduled is the kind of quiet wrongness that permanently costs a
 * customer's confidence in a payments screen.
 */
#[Layout('layouts.portal')]
#[Title('Payments')]
class Payments extends Component
{
    use ServesTheClient;

    /**
     * Receipts are generated on the server from the row itself.
     *
     * Plain text rather than a PDF: honest about what it is, and it still puts
     * a real file in the customer's hands with every figure taken from the
     * same record the agency's ledger reads.
     */
    public function receipt(int $paymentId): StreamedResponse
    {
        $p = $this->purchase();
        $payment = $p->payments->firstWhere('id', $paymentId);

        abort_if(! $payment || ! $payment->paid_at, 404);

        $customer = $this->customer();

        $lines = implode("\n", [
            mb_strtoupper(app('nexabi.tenant')->name).' — PAYMENT RECEIPT',
            '',
            sprintf('%-16s %s', 'Receipt no.', 'NX-R'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)),
            sprintf('%-16s %s', 'Customer', $customer->name),
            sprintf('%-16s %s', 'Property', ($p->property->title ?? '—').', '.($p->property->location ?? '')),
            sprintf('%-16s %s', 'Reference', $p->property->ref ?? '—'),
            '',
            sprintf('%-16s %s', 'Instalment', $payment->instalment.' of '.$payment->of_total),
            sprintf('%-16s %s', 'Amount', Fmt::money($payment->amount)),
            sprintf('%-16s %s', 'Due', Fmt::date($payment->due_at)),
            sprintf('%-16s %s', 'Paid', Fmt::date($payment->paid_at)),
            sprintf('%-16s %s', 'Method', $payment->method),
            '',
            sprintf('%-16s %s', 'Purchase price', Fmt::money($p->total())),
            sprintf('%-16s %s', 'Paid to date', Fmt::money($p->paidAmount())),
            sprintf('%-16s %s', 'Outstanding', Fmt::money($p->outstanding())),
            '',
            'Issued '.now()->format('j M Y, H:i'),
        ]);

        return response()->streamDownload(
            fn () => print($lines),
            'receipt-'.$payment->id.'.txt',
            ['Content-Type' => 'text/plain'],
        );
    }

    public function render()
    {
        $portfolio = \App\Support\Purchase::portfolio($this->customer());

        return view('livewire.portal.payments', [
            'customer' => $this->customer(),
            'p' => $this->purchase(),
            'next' => $this->purchase()->next(),

            // Everything else they hold. A portal that shows one purchase and
            // silently hides the rest is worse than one that never let them
            // buy twice — and browsing now actively encourages a second.
            'others' => $portfolio->reject(fn ($x) => $x->deal?->id === $this->purchase()->deal?->id),
            'awaiting' => \App\Support\Purchase::awaiting($this->customer()),
        ]);
    }
}
