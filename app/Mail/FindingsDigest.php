<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The morning digest.
 *
 * Ranked for the recipient's own seat, so the Finance Lead and the Sales
 * Manager get the same findings in a different order — the same weighting the
 * console applies, not a separate list that can drift from it.
 *
 * Sent only when there is something to say. A digest that arrives every day
 * whether or not anything happened is a digest people filter away.
 */
class FindingsDigest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Tenant $tenant,
        public array $insights,
        public array $headline,
        public string $brief,
        public float $impact,
    ) {}

    public function envelope(): Envelope
    {
        $top = $this->insights[0] ?? null;

        return new Envelope(
            subject: $top
                ? count($this->insights).' findings — starting with: '.$top->title
                : 'Your '.$this->tenant->name.' briefing',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.findings-digest');
    }
}
