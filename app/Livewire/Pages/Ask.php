<?php

namespace App\Livewire\Pages;

use App\Ai\AskEngine;
use App\Livewire\Concerns\InteractsWithEngine;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Ask NexABI — natural language over the metrics layer.
 *
 * Answers render as a CARD, not a paragraph: headline figure, delta, chart,
 * contributing signals, and a drill-through to the rows. That is better than a
 * chat bubble, and it happens to be exactly what a bounded-domain intent
 * router can deliver honestly.
 *
 * The parse is printed under every answer. When the router misreads a
 * question, the user can see that it did — which is the difference between a
 * tool you can calibrate and one you learn to distrust.
 */
#[Layout('layouts.app')]
#[Title('Ask NexABI')]
class Ask extends Component
{
    use InteractsWithEngine;

    #[Url(except: '')]
    public string $q = '';

    public string $draft = '';

    /** Newest first, so the answer you just asked for is at the top. */
    public array $answers = [];

    public function mount(): void
    {
        if ($this->q !== '') {
            $this->submit($this->q);
        }
    }

    public function submit(?string $question = null): void
    {
        $question = trim($question ?? $this->draft);

        if ($question === '') {
            return;
        }

        $engine = new AskEngine($this->tenant(), $this->metrics(), $this->engine());

        array_unshift($this->answers, $engine->ask($question));
        $this->draft = '';
        $this->q = '';
    }

    public function render()
    {
        $engine = new AskEngine($this->tenant(), $this->metrics(), $this->engine());

        return view('livewire.pages.ask', [
            'suggestions' => array_slice($engine->suggestions(), 0, 9),
        ]);
    }
}
