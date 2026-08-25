<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Feedback as FeedbackModel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Feedback — sentiment, recurring themes and the raw comments behind them.
 *
 * Tag frequency is the useful signal. An individual review is noise; a theme
 * repeating twenty-three times in sixty days is an operational defect, and
 * that is the distinction the themes panel exists to draw.
 */
#[Layout('layouts.app')]
#[Title('Feedback')]
class Feedback extends Component
{
    use InteractsWithEngine, WithPagination;

    #[Url(except: '')] public string $tag = '';
    #[Url(except: '')] public string $rating = '';
    #[Url(except: '')] public string $q = '';

    public function updated($property): void
    {
        if (in_array($property, ['tag', 'rating', 'q'], true)) {
            $this->resetPage();
        }
    }

    public function toggleTag(string $tag): void
    {
        $this->tag = $this->tag === $tag ? '' : $tag;
        $this->resetPage();
    }

    #[On('engine-changed')]
    public function refresh(): void
    {
        $this->metrics()->flush();
    }

    public function render()
    {
        $fb = $this->metrics()->feedback(60);

        $rows = FeedbackModel::query()
            ->with(['customer:id,name', 'agent:id,name'])
            // tags is a JSON column; whereJsonContains keeps the filter in the
            // database rather than pulling every review into PHP to sift.
            ->when($this->tag, fn ($qq) => $qq->whereJsonContains('tags', $this->tag))
            ->when($this->rating === 'low', fn ($qq) => $qq->where('rating', '<=', 2))
            ->when($this->rating === 'mid', fn ($qq) => $qq->where('rating', 3))
            ->when($this->rating === 'high', fn ($qq) => $qq->where('rating', '>=', 4))
            ->when($this->q, fn ($qq) => $qq->where('text', 'like', '%'.$this->q.'%'))
            ->orderByDesc('created_at')
            ->paginate(40);

        return view('livewire.pages.feedback', [
            'fb' => $fb,
            'rows' => $rows,
            'charts' => [
                'distribution' => ['data' => $fb['distribution']],
            ],
        ]);
    }
}
