<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ServesTheClient;
use App\Models\Property;
use App\Platform\Commitments;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Committing to a property.
 *
 * The investor picks their own term, and the monthly figure updates as they
 * move it — that number is the entire decision, and burying it behind a submit
 * button means people guess and then regret.
 *
 * Nothing is sold here. Committing holds the unit and opens a request; an
 * admin approves it, and only then does the instalment plan exist.
 */
#[Layout('layouts.portal')]
class Commit extends Component
{
    use ServesTheClient;

    public Property $property;

    public int $months = 12;

    public bool $agreed = false;

    public function mount(string $ref): void
    {
        $this->property = Property::where('ref', $ref)->firstOrFail();

        if (! $this->property->isOnMarket()) {
            $this->redirectRoute('portal.browse', navigate: true);

            return;
        }

        $options = $this->property->durationOptions();
        $this->months = in_array(12, $options, true) ? 12 : $options[0];
    }

    public function commit(): void
    {
        if (! $this->agreed) {
            $this->addError('agreed', 'Please confirm you have read the terms.');

            return;
        }

        $result = app(Commitments::class)
            ->open($this->customer(), $this->property, $this->months);

        if (! $result['ok']) {
            $this->addError('months', $result['message']);

            return;
        }

        session()->flash('status', $result['message']);
        $this->redirectRoute('portal.overview', navigate: true);
    }

    public function render()
    {
        return view('livewire.portal.commit', [
            'customer' => $this->customer(),
            'options' => $this->property->durationOptions(),
            'perMonth' => $this->property->instalmentFor($this->months),
        ])->title('Commit to '.$this->property->title);
    }
}
