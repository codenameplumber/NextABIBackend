<?php

namespace App\Console\Commands;

use App\Engine\InsightEngine;
use App\Engine\Metrics;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Scheduled detection.
 *
 * This is the half of the loop a browser could never have. The client-side
 * prototype only ever noticed a problem while someone was looking at it; here
 * the detectors run on a schedule across every tenant, findings appear on
 * their own, and `resolved_at` gets stamped when a condition stops holding —
 * which is what makes "how long did this go unfixed" answerable at all.
 */
class RunDetectors extends Command
{
    protected $signature = 'nexabi:detect
        {--tenant= : Slug or id of a single tenant, otherwise all}
        {--only=* : Detector keys to run, otherwise all fourteen}';

    protected $description = 'Run the detectors and reconcile findings for every tenant';

    public function handle(): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::where('slug', $this->option('tenant'))
                ->orWhere('id', $this->option('tenant'))->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('No tenant matched.');

            return self::FAILURE;
        }

        $only = $this->option('only') ?: null;

        foreach ($tenants as $tenant) {
            $started = microtime(true);

            // Bound explicitly: there is no authenticated user in a console
            // run, and the global scope on every model reads this binding.
            app()->instance('nexabi.tenant', $tenant);

            // The metrics cache is per tenant and holds aggregates from the
            // last request. A scheduled run that reads yesterday's cache finds
            // yesterday's problems.
            Metrics::for($tenant)->flush();

            $live = InsightEngine::for($tenant)->run($only);
            $tenant->forceFill(['detectors_ran_at' => now()])->save();

            $this->line(sprintf(
                '  %-28s %3d live findings   %sms',
                $tenant->name,
                $live,
                number_format((microtime(true) - $started) * 1000),
            ));
        }

        return self::SUCCESS;
    }
}
