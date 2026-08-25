<?php

namespace App\Providers;

use App\Ai\NarratorContract;
use App\Ai\OpenAiNarrator;
use App\Ai\TemplateNarrator;
use App\Engine\ActionBus;
use App\Engine\InsightEngine;
use App\Engine\Metrics;
use App\Engine\PlaybookRegistry;
use App\Models\Tenant;
use Illuminate\Support\ServiceProvider;

class NexabiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlaybookRegistry::class);
        $this->app->singleton(TemplateNarrator::class);

        /**
         * One engine instance per request, shared by every component.
         *
         * Metrics memoises inside the instance, so without this a page
         * carrying a dashboard, a nav and four insight cards would issue the
         * same nine aggregate queries six times over. `scoped` rather than
         * `singleton` so a queue worker gets a fresh one per job instead of
         * serving a second tenant from the first tenant's memo.
         */
        $this->app->scoped(Metrics::class, fn ($app) => new Metrics($app->make('nexabi.tenant')));
        $this->app->scoped(InsightEngine::class, fn ($app) => new InsightEngine($app->make('nexabi.tenant')));
        $this->app->scoped(ActionBus::class, fn ($app) => new ActionBus($app->make('nexabi.tenant')));

        /**
         * One binding, two implementations.
         *
         * Swapping the provider is a config change, not a code change — layers
         * 2 through 7 only ever receive a string and never learn where it came
         * from. With no key configured, OpenAiNarrator delegates everything to
         * the templated narrator and the product works unchanged.
         */
        $this->app->singleton(NarratorContract::class, fn ($app) => new OpenAiNarrator(
            $app->make(TemplateNarrator::class),
            config('services.openai.enabled') ? config('services.openai.key') : null,
            config('services.openai.model', 'gpt-4o-mini'),
        ));

        /**
         * The tenant for the current request.
         *
         * Bound lazily so console commands and queued jobs can override it with
         * app()->instance() before anything reads it.
         */
        $this->app->bind('nexabi.tenant', fn () => auth()->user()?->tenant ?? Tenant::first());
    }
}
