<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Insight;
use App\Models\Recommendation;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The InsightCard — the single most important component in the product.
 *
 * It is where four layers become visible at once:
 *
 *   title      ← detector    (L3)
 *   narrative  ← AI seam     (L4)
 *   evidence   ← metrics     (L2, one click from the rows that produced it)
 *   actions    ← playbooks   (L5) executed through the action bus (L6)
 *
 * When a recommendation is applied the database mutates, the invalidated
 * detectors re-run, and this card retires itself because the condition no
 * longer holds. Nothing about that is scripted — if the action fails to change
 * anything, the card stays exactly where it is. That is the whole claim of the
 * product, and it is enforced here rather than asserted in a slide.
 */
class InsightCard extends Component
{
    use InteractsWithEngine;

    #[Locked]
    public int $insightId;

    public bool $compact = false;

    /** Set once an action retires the finding, so the card can bow out. */
    public bool $retired = false;

    public ?string $lastResult = null;

    public function mount(Insight|int $insight, bool $compact = false): void
    {
        $this->insightId = $insight instanceof Insight ? $insight->id : $insight;
        $this->compact = $compact;
    }

    public function insight(): ?Insight
    {
        return Insight::with('recommendations')->find($this->insightId);
    }

    public function apply(int $recommendationId): void
    {
        $rec = Recommendation::where('insight_id', $this->insightId)->find($recommendationId);

        if (! $rec) {
            $this->toast('That recommendation is no longer available.', kind: 'neg');

            return;
        }

        $result = $this->bus()->execute($rec, auth()->id());

        if (! $result['ok']) {
            $this->toast('Not applied', $result['message'], 'neg');

            return;
        }

        // The before → after line is the proof. It is the handler's own
        // measurement of the world either side of the change, not a caption.
        $this->lastResult = isset($result['before'], $result['after'])
            ? $result['before'].'  →  '.$result['after']
            : null;

        $this->toast($rec->label, $result['message'], 'pos');

        // Did the finding survive its own fix?
        $fresh = $this->insight();
        $this->retired = ! $fresh || ! $fresh->isLive();

        if ($this->retired) {
            $this->toast(
                'Finding retired',
                'The condition no longer holds, so NexABI closed it.',
                'ai',
            );
        }

        // Nav badges, dashboard counts and the feed all read from the engine,
        // so one event is enough to make the whole page honest again.
        $this->dispatch('engine-changed');
    }

    public function dismiss(): void
    {
        $insight = $this->insight();

        if ($insight) {
            // Dismissal is recorded against the fingerprint, so re-detection
            // does not resurrect something the user has already judged.
            $insight->forceFill(['dismissed_at' => now(), 'status' => 'dismissed'])->save();
        }

        $this->retired = true;
        $this->dispatch('engine-changed');
        $this->toast('Dismissed', 'It will not come back unless the numbers change materially.');
    }

    public function render()
    {
        $insight = $this->insight();

        return view('livewire.insight-card', [
            'insight' => $insight,
            'narrative' => $insight ? $this->narrator()->narrate($insight) : '',
        ]);
    }
}
