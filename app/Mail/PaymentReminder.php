<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * The reminder the SEND_REMINDERS action claims to send.
 *
 * Until this existed, that action reported "19 reminders issued" and issued
 * nothing — the counter moved and no buyer ever heard from anyone. An action
 * that says it did something has to have done it.
 *
 * Tone matters here: a late instalment on this plan carries no penalty, so the
 * mail says that plainly and offers to restructure rather than implying a
 * threat nobody intends to carry out.
 */
class PaymentReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public Collection $payments,
    ) {}

    public function envelope(): Envelope
    {
        $n = $this->payments->count();

        return new Envelope(
            subject: $n === 1
                ? 'A payment on your property is due'
                : "{$n} payments on your property are due",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payment-reminder', with: [
            'total' => (float) $this->payments->sum('amount'),
            'worst' => (int) $this->payments->max(fn ($p) => $p->lateBy()),
        ]);
    }
}
