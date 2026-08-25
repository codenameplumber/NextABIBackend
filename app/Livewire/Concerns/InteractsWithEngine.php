<?php

namespace App\Livewire\Concerns;

use App\Ai\NarratorContract;
use App\Engine\ActionBus;
use App\Engine\InsightEngine;
use App\Engine\Metrics;
use App\Models\Tenant;

/**
 * The engine handles every Livewire page uses.
 *
 * One Metrics instance per request, shared across every component on the page.
 * That matters: Metrics memoises inside the instance, so a dashboard that
 * builds its own would issue the same aggregate queries a second time.
 */
trait InteractsWithEngine
{
    public function tenant(): Tenant
    {
        return app('nexabi.tenant');
    }

    /**
     * One Metrics instance per request, shared by every component on the page.
     *
     * This matters more than it looks. Metrics memoises inside the instance,
     * so a page carrying a dashboard, a nav and four insight cards would
     * otherwise issue the same nine aggregate queries six times over. Resolved
     * from the container rather than from `once()` precisely because `once()`
     * is scoped per object, and these are six different objects.
     */
    public function metrics(): Metrics
    {
        return app(Metrics::class);
    }

    public function engine(): InsightEngine
    {
        return app(InsightEngine::class);
    }

    public function bus(): ActionBus
    {
        return app(ActionBus::class);
    }

    public function narrator(): NarratorContract
    {
        return app(NarratorContract::class);
    }

    /** The lens the current user is looking through. */
    public function role(): string
    {
        return session('nexabi.role', auth()->user()?->role ?? 'ceo');
    }

    protected function toast(string $title, string $msg = '', string $kind = ''): void
    {
        $this->dispatch('toast', ['title' => $title, 'msg' => $msg, 'kind' => $kind]);
    }
}
