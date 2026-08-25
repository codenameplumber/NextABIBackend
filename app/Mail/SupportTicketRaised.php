<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To the agent, the moment a buyer raises something.
 *
 * The reply-to is the customer, so hitting reply in a mail client goes to the
 * person who asked rather than to a no-reply address.
 */
class SupportTicketRaised extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportTicket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.$this->ticket->reference.'] '.$this->ticket->topic
                .' — '.$this->ticket->customer->name,
            replyTo: array_filter([$this->ticket->customer->email]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.support-ticket-raised', with: [
            // Context the agent would otherwise go and look up. Whether this
            // person is behind on payments changes how the reply should read.
            'outstanding' => (float) $this->ticket->customer->payments()
                ->whereNull('paid_at')->where('due_at', '<=', now())->sum('amount'),
        ]);
    }
}
