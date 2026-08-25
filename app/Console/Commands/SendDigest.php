<?php

namespace App\Console\Commands;

use App\Ai\NarratorContract;
use App\Engine\InsightEngine;
use App\Engine\Metrics;
use App\Mail\FindingsDigest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * The morning briefing.
 *
 * Ranked per recipient using the same role weights the console applies, so the
 * Finance Lead and the Sales Manager get one list in two orders rather than
 * two lists that can drift apart.
 *
 * Sent only when there is something to say, unless --force. A digest that
 * arrives every morning whether or not anything happened is a digest people
 * filter into a folder they never open.
 */
class SendDigest extends Command
{
    protected $signature = 'nexabi:digest
        {--tenant= : Slug or id of a single tenant, otherwise all}
        {--force : Send even when nothing is flagged}';

    protected $description = 'Email each staff member the findings ranked for their seat';

    public function handle(): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::where('slug', $this->option('tenant'))
                ->orWhere('id', $this->option('tenant'))->get()
            : Tenant::all();

        $sent = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            app()->instance('nexabi.tenant', $tenant);

            $metrics = Metrics::for($tenant);
            $metrics->flush();

            $engine = InsightEngine::for($tenant);
            $live = $engine->live();

            if (! $live && ! $this->option('force')) {
                $this->line("  {$tenant->name} — nothing flagged, not sending");
                $skipped++;

                continue;
            }

            $headline = $metrics->headline();
            $narrator = app(NarratorContract::class);

            foreach (User::where('tenant_id', $tenant->id)->where('is_client', false)->get() as $user) {
                $feed = $engine->feed($user->role ?? 'ceo', 8);

                try {
                    Mail::to($user->email)->send(new FindingsDigest(
                        user: $user,
                        tenant: $tenant,
                        insights: $feed,
                        headline: $headline,
                        brief: $narrator->brief($headline, $feed),
                        impact: $engine->impactOnTable($feed),
                    ));
                    $sent++;
                } catch (\Throwable $e) {
                    $this->error("  {$user->email} — ".$e->getMessage());
                    report($e);
                }
            }

            $this->line(sprintf('  %-28s %d findings → %d recipients', $tenant->name, count($live), $sent));
        }

        $this->info("Digest: {$sent} sent, {$skipped} tenants skipped (nothing to report).");

        return self::SUCCESS;
    }
}
