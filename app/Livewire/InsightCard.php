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

    /**
     * Whether this card carries an executable action.
     *
     * Off by default, and that is the important half. On the business modules
     * a finding is context for the table underneath it — someone reading the
     * Leads screen wants to know their follow-up is slipping, not to be handed
     * a button mid-scroll. Those cards say what is happening, what it means
     * and what to do about it, then offer Ask NexABI for the detail.
     *
     * The Insights screen turns it on, because that is the one place a person
     * arrived specifically to decide something. The closed loop is unchanged:
     * the same ActionBus, the same before-and-after diff, the same finding
     * retiring itself. It is reachable from one screen instead of six.
     */
    public bool $showActions = false;

    /** Set once an action retires the finding, so the card can bow out. */
    public bool $retired = false;

    public ?string $lastResult = null;

    public function mount(Insight|int $insight, bool $compact = false, bool $showActions = false): void
    {
        $this->insightId = $insight instanceof Insight ? $insight->id : $insight;
        $this->compact = $compact;
        $this->showActions = $showActions;
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
            // The single thing to do about it, rendered as a sentence rather
            // than a control. Its label and rationale were always written to
            // be read — they were just buried under a button.
            'todo' => $insight?->primaryRecommendation(),
            // Carries the question AND the finding's id. The question is what
            // the reader sees typed into the box; the id is what guarantees the
            // answer is about this finding rather than whatever the router
            // managed to make of the sentence.
            'ask' => $insight
                ? route('app.ask', ['q' => $insight->askQuestion(), 'from' => $insight->id])
                : route('app.ask'),
        ]);
    }
}
