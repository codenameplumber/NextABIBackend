<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent once, when someone provisions a new agency. */
class AgencyWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your agency is set up on '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.agency-welcome', with: [
            'tenant' => $this->user->tenant,
        ]);
    }
}
