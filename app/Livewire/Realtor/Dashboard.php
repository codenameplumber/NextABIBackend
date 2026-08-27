<?php

namespace App\Livewire\Realtor;

use App\Livewire\Realtor\Concerns\IsARealtor;
use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A realtor's own numbers.
 *
 * Three questions, in the order they are actually asked: who have I brought in,
 * what have they committed to, and what am I owed. Commission is split into
 * earned and paid rather than shown as one figure — an obligation and a
 * transaction are different things, and a realtor knows the difference.
 */
#[Layout('layouts.realtor')]
#[Title('My dashboard')]
class Dashboard extends Component
{
    use IsARealtor;

    public function render()
    {
        $realtor = $this->realtor();

        $investors = Customer::where('agent_id', $realtor->id);
        $commitments = Deal::where('agent_id', $realtor->id);
        $payouts = CommissionPayout::where('agent_id', $realtor->id);

        return view('livewire.realtor.dashboard', [
            'realtor' => $realtor,
            'leadCount' => Lead::where('assigned_agent_id', $realtor->id)->count(),
            'openLeads' => Lead::where('assigned_agent_id', $realtor->id)
                ->where('is_open', true)->count(),
            'investorCount' => (clone $investors)->count(),
            'pending' => (clone $commitments)->where('status', 'pending')->count(),
            'live' => (clone $commitments)->whereIn('status', Deal::LIVE)->count(),
            'completed' => (clone $commitments)->where('status', 'completed')->count(),
            'salesValue' => (float) (clone $commitments)
                ->whereIn('status', array_merge(Deal::LIVE, ['completed']))->sum('value'),
            'earned' => (float) (clone $payouts)->sum('amount'),
            'paid' => (float) (clone $payouts)->where('status', 'paid')->sum('amount'),
            'owed' => (float) (clone $payouts)->where('status', 'owed')->sum('amount'),
            'recent' => Deal::where('agent_id', $realtor->id)
                ->with(['customer', 'property'])
                ->orderByDesc('committed_at')->limit(6)->get(),
        ]);
    }
}
