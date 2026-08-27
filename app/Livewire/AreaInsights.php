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

    /**
     * One card. Not two, and never "however many were found".
     *
     * A module screen exists to show its own table; the finding above it is
     * there to tell you what to look at first. Two findings is already a
     * ranking exercise the reader did not ask to do, and the ranking has
     * already been done — priority is severity × confidence × role weight ×
     * impact, so the top one IS the answer to "what matters here".
     *
     * The rest are not hidden: the header says how many there are and links
     * to all of them.
     */
    public int $limit = 1;

    public function mount(string $area, int $limit = 1): void
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
