<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The buyer's receipt. Says what happens next and by when. */
class SupportTicketReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportTicket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We have your request ('.$this->ticket->reference.')',
            replyTo: array_filter([$this->ticket->agent?->email]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.support-ticket-received');
    }
}
