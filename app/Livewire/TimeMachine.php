<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Support\Fmt;
use App\Support\TimeMachine as Engine;
use Livewire\Component;

/**
 * The topbar clock control.
 *
 * The literal demonstration of "Adaptive": advance the business and the
 * findings change on their own, because they were never anything but a
 * reading of the rows.
 */
class TimeMachine extends Component
{
    use InteractsWithEngine;

    public function advance(int $days): void
    {
        $summary = (new Engine($this->tenant()))->advance($days);

        $this->metrics()->flush();
        $found = $this->engine()->run();

        $this->dispatch('engine-changed');

        $this->toast(
            "Clock advanced {$days} days",
            Engine::describe($summary)
                .' · '.Fmt::moneyShort($summary['revenue']).' closed'
                .' · detectors re-ran, '.$found.' live findings',
            'ai',
        );
    }

    public function render()
    {
        return view('livewire.time-machine');
    }
}
