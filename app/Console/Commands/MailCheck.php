<?php

namespace App\Console\Commands;

use App\Ai\NarratorContract;
use App\Engine\InsightEngine;
use App\Engine\Metrics;
use App\Mail\AgencyWelcome;
use App\Mail\InvestorWelcome;
use App\Mail\RealtorApproved;
use App\Mail\FindingsDigest;
use App\Mail\PaymentReminder;
use App\Mail\SupportTicketRaised;
use App\Mail\SupportTicketReceived;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Send one of every email to a single address.
 *
 * The point is to see them, not to test them — six real messages land in a
 * Mailtrap inbox and you can read what a customer would actually receive. The
 * test suite proves they send; this proves they are worth receiving.
 */
class MailCheck extends Command
{
    protected $signature = 'nexabi:mailcheck
        {to? : Where to send them, otherwise the configured from-address}
        {--only= : Just one — welcome|approved|agency|ticket|receipt|reminder|digest|reset}';

    protected $description = 'Send one of each email to a single address so you can read them';

    public function handle(): int
    {
        $to = $this->argument('to') ?: config('mail.from.address');
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('No tenant. Run: php artisan migrate:fresh --seed');

            return self::FAILURE;
        }

        app()->instance('nexabi.tenant', $tenant);

        $this->line('  mailer  '.config('mail.default'));
        $this->line('  host    '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
        $this->line('  to      '.$to);

        if (config('mail.default') === 'smtp' && blank(config('mail.mailers.smtp.username'))) {
            $this->warn('  MAIL_USERNAME is blank — set your Mailtrap credentials in .env first.');
        }

        $this->newLine();

        foreach ($this->build($tenant) as $name => $make) {
            if ($this->option('only') && $this->option('only') !== $name) {
                continue;
            }

            $mail = $make();

            // Render first. A broken template and an unreachable mail server
            // are different problems and deserve different messages.
            try {
                $bytes = mb_strlen($mail->render());
            } catch (\Throwable $e) {
                $this->line(sprintf('  <fg=red>TEMPLATE</> %-10s %s', $name, $e->getMessage()));

                continue;
            }

            try {
                Mail::to($to)->send($mail);
                $this->line(sprintf('  <fg=green>sent</> %-12s %s bytes', $name, number_format($bytes)));
            } catch (\Throwable $e) {
                $this->line(sprintf('  <fg=red>SEND</> %-12s %s', $name, $e->getMessage()));
            }
        }

        $this->newLine();
        $this->info(config('mail.default') === 'log'
            ? 'MAIL_MAILER=log — check storage/logs/laravel.log.'
            : 'Check your Mailtrap inbox.');

        return self::SUCCESS;
    }

    /** @return array<string, callable> */
    private function build(Tenant $tenant): array
    {
        $staff = User::where('is_client', false)->firstOrFail();

        // A ticket to show, without leaving one behind in the customer's file.
        $ticket = SupportTicket::with(['customer', 'agent'])->latest('id')->first()
            ?? new SupportTicket([
                'tenant_id' => $tenant->id,
                'customer_id' => Customer::value('id'),
                'agent_id' => Agent::value('id'),
                'reference' => 'TCK-SAMPLE',
                'topic' => 'Change my payment plan',
                'body' => 'The next instalment falls the same week as my rent. '
                    .'Could we move it back a fortnight?',
                'status' => 'open',
                'reply' => 'Received. Your agent has been notified and will respond '
                    .'within one working day.',
                // Unsaved, so Eloquent has not stamped these — and the template
                // prints when the ticket was raised.
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $ticket->setRelation('customer', $ticket->customer ?? Customer::first());
        $ticket->setRelation('agent', $ticket->agent ?? Agent::first());

        $late = Payment::whereNull('paid_at')->where('due_at', '<', now())
            ->orderBy('due_at')->limit(3)->get();

        $investor = \App\Models\User::where('type', 'investor')->first()
            ?? \App\Models\User::first();

        $engine = InsightEngine::for($tenant);
        $metrics = Metrics::for($tenant);
        $feed = $engine->feed($staff->role ?? 'ceo', 8);

        return [
            // The two the platform actually sends on signup and approval.
            // AgencyWelcome is kept because the Mailable still exists and a
            // broken template should be caught here rather than in production.
            'welcome' => fn () => new InvestorWelcome(
                $investor,
                $investor->realtor ?? Agent::query()->approved()->first(),
            ),
            'approved' => fn () => new RealtorApproved(
                Agent::query()->approved()->whereNotNull('code')->first() ?? Agent::first(),
            ),
            'agency' => fn () => new AgencyWelcome($staff),
            'ticket' => fn () => new SupportTicketRaised($ticket),
            'receipt' => fn () => new SupportTicketReceived($ticket),
            'reminder' => fn () => new PaymentReminder(
                $late->first()?->customer ?? Customer::first(),
                $late,
            ),
            'digest' => fn () => new FindingsDigest(
                $staff,
                $tenant,
                $feed,
                $metrics->headline(),
                app(NarratorContract::class)->brief($metrics->headline(), $feed),
                $engine->impactOnTable($feed),
            ),
        ];
    }
}
