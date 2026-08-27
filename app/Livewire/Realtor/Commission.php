<?php

namespace App\Livewire\Realtor;

use App\Livewire\Realtor\Concerns\IsARealtor;
use App\Models\CommissionPayout;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The commission statement.
 *
 * Line by line, with the rate that was actually applied shown against each
 * one. Rates differ per property and change over time, so a single blended
 * percentage would leave a realtor unable to check any individual figure —
 * and a statement you cannot check is one you do not trust.
 */
#[Layout('layouts.realtor')]
#[Title('My commission')]
class Commission extends Component
{
    use IsARealtor;

    public function render()
    {
        $rows = CommissionPayout::where('agent_id', $this->realtor()->id)
            ->with(['commitment.property', 'commitment.customer'])
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.realtor.commission', [
            'realtor' => $this->realtor(),
            'rows' => $rows,
            'earned' => (float) $rows->sum('amount'),
            'paid' => (float) $rows->where('status', 'paid')->sum('amount'),
            'owed' => (float) $rows->where('status', 'owed')->sum('amount'),
        ]);
    }
}
