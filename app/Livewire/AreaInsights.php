<?php

namespace App\Livewire;

use App\Engine\InsightEngine;
use App\Livewire\Concerns\InteractsWithEngine;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The insight strip that sits above the data on every business module.
 *
 * Putting it above the tables is the whole point: the numbers are the
 * evidence, not the headline. It renders nothing at all when the area is
 * clean, rather than an empty box announcing that nothing is wrong.
 */
class AreaInsights extends Component
{
    use InteractsWithEngine;

    #[Locked]
    public string $area;

    public int $limit = 2;

    public function mount(string $area, int $limit = 2): void
    {
        $this->area = $area;
        $this->limit = $limit;
    }

    #[On('engine-changed')]
    public function refresh(): void
    {
        //
    }

    public function render()
    {
        $items = array_slice($this->engine()->live($this->area), 0, $this->limit);

        return view('livewire.area-insights', [
            'items' => $items,
            'total' => count($this->engine()->live($this->area)),
            'label' => InsightEngine::AREAS[$this->area] ?? $this->area,
        ]);
    }
}
