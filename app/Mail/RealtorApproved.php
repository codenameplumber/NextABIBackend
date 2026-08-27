<?php

namespace App\Mail;

use App\Models\Agent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email the holding screen promises.
 *
 * Carries the referral code and the registration link, because those are the
 * two things a newly approved realtor needs and the only reason they have been
 * waiting. An approval notice that says "you are approved, sign in to find out
 * more" wastes the one message somebody will definitely read.
 */
class RealtorApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Agent $realtor) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You are approved — here is your code');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.realtor-approved');
    }
}
