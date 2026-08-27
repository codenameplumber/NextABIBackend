<?php

namespace App\Mail;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once, when somebody registers as an investor.
 *
 * Names their realtor when they have one. An investor who registered through a
 * link already believes somebody is looking after them, and the email either
 * confirms that or quietly tells them one is coming.
 */
class InvestorWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?Agent $realtor = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.investor-welcome');
    }
}
