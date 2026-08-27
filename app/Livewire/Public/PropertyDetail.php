<?php

namespace App\Livewire\Public;

use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One property, in full.
 *
 * Shows the instalment terms before asking for anything — the whole question a
 * buyer has is "what would this cost me a month", and making them register to
 * find out is how a catalogue becomes a dead end.
 *
 * Commission is deliberately absent. What the agency earns is not the buyer's
 * business, and showing it invites a negotiation nobody wants to have.
 */
#[Layout('components.layouts.marketing')]
class PropertyDetail extends Component
{
    public Property $property;

    public int $months = 12;

    public function mount(string $ref): void
    {
        $this->property = Property::where('ref', $ref)->firstOrFail();

        $options = $this->property->durationOptions();
        $this->months = in_array(12, $options, true) ? 12 : $options[0];
    }

    public function render()
    {
        return view('livewire.public.property-detail', [
            'options' => $this->property->durationOptions(),
            'perMonth' => $this->property->instalmentFor($this->months),
            'similar' => Property::query()->onMarket()
                ->where('id', '!=', $this->property->id)
                ->where('location', $this->property->location)
                ->limit(3)->get(),
        ])->title($this->property->title.' — NexABI');
    }
}
