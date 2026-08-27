<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ServesTheClient;
use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What an investor can buy.
 *
 * The same stock as the public catalogue, inside the portal so a signed-in
 * investor can go from browsing to committing without being bounced back out
 * to a marketing page and asked to log in again.
 */
#[Layout('layouts.portal')]
#[Title('Browse properties')]
class Browse extends Component
{
    use ServesTheClient, WithPagination;

    #[Url(as: 'q', keep: false)]
    public string $search = '';

    #[Url(as: 'where', keep: false)]
    public string $location = '';

    #[Url(as: 'max', keep: false)]
    public string $maxPrice = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $rows = Property::query()->onMarket()
            ->when($this->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$s}%")
                ->orWhere('location', 'like', "%{$s}%")))
            ->when($this->location, fn ($q, $v) => $q->where('location', $v))
            ->when($this->maxPrice, fn ($q, $v) => $q->where('price', '<=', (float) $v))
            ->orderByDesc('listed_at')
            ->paginate(9);

        return view('livewire.portal.browse', [
            'customer' => $this->customer(),
            'rows' => $rows,
            'locations' => Property::query()->onMarket()
                ->distinct()->orderBy('location')->pluck('location'),
            // What they already hold, so the catalogue can say so rather than
            // letting somebody commit twice and discover it at the refusal.
            'held' => \App\Models\Deal::where('customer_id', $this->customer()->id)
                ->whereIn('status', array_merge(['pending'], \App\Models\Deal::LIVE))
                ->pluck('property_id')->all(),
        ]);
    }
}
