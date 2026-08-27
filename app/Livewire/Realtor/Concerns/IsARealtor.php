<?php

namespace App\Livewire\Realtor\Concerns;

use App\Models\Agent;

/**
 * Shared plumbing for the realtor area.
 *
 * The realtor is resolved from the signed-in user and never from a request
 * parameter. An area where the realtor is addressable is one where somebody
 * reads a colleague's book of business — the same failure the tenant scope
 * exists to prevent, one level down.
 */
trait IsARealtor
{
    public function realtor(): Agent
    {
        return once(fn () => Agent::findOrFail(auth()->user()->agent_id));
    }

    protected function toast(string $title, string $msg = '', string $kind = ''): void
    {
        $this->dispatch('toast', ['title' => $title, 'msg' => $msg, 'kind' => $kind]);
    }
}
