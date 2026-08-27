<?php

namespace App\Livewire\Realtor;

use App\Ai\RealtorAdvisor;
use App\Livewire\Realtor\Concerns\IsARealtor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ask NexABI, from the realtor's side.
 *
 * Demand is answered market-wide because that is the signal a realtor is
 * paying attention for; performance is answered only from their own book.
 * The advisor keeps the two apart — nothing here can name another realtor's
 * numbers.
 */
#[Layout('layouts.realtor')]
#[Title('Ask NexABI')]
class Ask extends Component
{
    use IsARealtor;

    public string $draft = '';

    public array $answers = [];

    public function submit(?string $question = null): void
    {
        $question = trim($question ?? $this->draft);

        if ($question === '') {
            return;
        }

        $advisor = new RealtorAdvisor($this->realtor());

        array_unshift($this->answers, $advisor->ask($question) + ['question' => $question]);
        $this->draft = '';
    }

    public function render()
    {
        return view('livewire.realtor.ask', [
            'realtor' => $this->realtor(),
            'suggestions' => (new RealtorAdvisor($this->realtor()))->suggestions(),
        ]);
    }
}
