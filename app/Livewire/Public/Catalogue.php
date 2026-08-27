<?php

namespace App\Livewire\Public;

use App\Models\Property;
use App\Support\Nigeria;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The public catalogue.
 *
 * Open to anyone. A property platform that demands registration before it will
 * show a price is one people leave — the price is the reason they came, and
 * withholding it buys an email address at the cost of the visit.
 */
#[Layout('components.layouts.marketing')]
#[Title('Available properties')]
class Catalogue extends Component
{
    use WithPagination;

    #[Url(as: 'q', keep: false)]
    public string $search = '';

    #[Url(as: 'where', keep: false)]
    public string $location = '';

    #[Url(as: 'type', keep: false)]
    public string $type = '';

    #[Url(as: 'max', keep: false)]
    public string $maxPrice = '';

    #[Url(as: 'sort', keep: false)]
    public string $sort = 'newest';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->reset(['search', 'location', 'type', 'maxPrice']);
        $this->resetPage();
    }

    public function render()
    {
        $base = fn () => Property::query()->onMarket();

        $rows = $base()
            ->when($this->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$s}%")
                ->orWhere('location', 'like', "%{$s}%")
                ->orWhere('ref', 'like', "%{$s}%")))
            ->when($this->location, fn ($q, $v) => $q->where('location', $v))
            ->when($this->type, fn ($q, $v) => $q->where('type', $v))
            ->when($this->maxPrice, fn ($q, $v) => $q->where('price', '<=', (float) $v))
            ->when($this->sort === 'cheapest', fn ($q) => $q->orderBy('price'))
            ->when($this->sort === 'dearest', fn ($q) => $q->orderByDesc('price'))
            ->when($this->sort === 'newest', fn ($q) => $q->orderByDesc('listed_at'))
            ->paginate(12);

        return view('livewire.public.catalogue', [
            'rows' => $rows,
            'locations' => $base()->distinct()->orderBy('location')->pluck('location'),
            'types' => $base()->distinct()->orderBy('type')->pluck('type'),
            'total' => $base()->count(),
        ]);
    }
}
