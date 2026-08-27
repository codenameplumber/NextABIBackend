<?php

namespace App\Livewire\Realtor;

use App\Livewire\Realtor\Concerns\IsARealtor;
use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The full catalogue, as a realtor sees it.
 *
 * Same stock as the public page with one addition: what this sale is worth to
 * them. A realtor deciding which unit to push should be able to see that,
 * and hiding it just means they work it out on a calculator anyway.
 */
#[Layout('layouts.realtor')]
#[Title('Properties')]
class Properties extends Component
{
    use IsARealtor, WithPagination;

    #[Url(as: 'q', keep: false)]
    public string $search = '';

    #[Url(as: 'where', keep: false)]
    public string $location = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $rows = Property::query()->onMarket()
            ->when($this->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$s}%")
                ->orWhere('ref', 'like', "%{$s}%")))
            ->when($this->location, fn ($q, $v) => $q->where('location', $v))
            ->orderByDesc('listed_at')
            ->paginate(12);

        return view('livewire.realtor.properties', [
            'realtor' => $this->realtor(),
            'rows' => $rows,
            'locations' => Property::query()->onMarket()
                ->distinct()->orderBy('location')->pluck('location'),
        ]);
    }
}
