<?php

namespace App\Livewire\Portal;

use App\Ai\ClientAdvisor;
use App\Livewire\Concerns\ServesTheClient;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ask NexABI, from the buyer's side.
 *
 * The questions a person buying on a schedule actually has are mostly
 * arithmetic they should not have to do — what fits this monthly figure, what
 * do I still owe, what happens if I am late. The advisor computes those and
 * only sends the open-ended ones to the model.
 */
#[Layout('layouts.portal')]
#[Title('Ask NexABI')]
class Ask extends Component
{
    use ServesTheClient;

    public string $draft = '';

    /** Newest first, so the answer just asked for is at the top. */
    public array $answers = [];

    public function submit(?string $question = null): void
    {
        $question = trim($question ?? $this->draft);

        if ($question === '') {
            return;
        }

        $advisor = new ClientAdvisor($this->customer());

        array_unshift($this->answers, $advisor->ask($question) + ['question' => $question]);
        $this->draft = '';
    }

    public function render()
    {
        return view('livewire.portal.ask', [
            'customer' => $this->customer(),
            'suggestions' => (new ClientAdvisor($this->customer()))->suggestions(),
        ]);
    }
}
