<?php

namespace App\Livewire\Realtor;

use App\Livewire\Realtor\Concerns\IsARealtor;
use App\Models\PromotionalMaterial;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Promotional materials, already theirs to send.
 *
 * One base asset is stored; the realtor's own code and link are stamped in on
 * the way out. The alternative — a copy per realtor — means a corrected price
 * has to be re-cut a hundred times, and it never is.
 */
#[Layout('layouts.realtor')]
#[Title('Promotional materials')]
class Materials extends Component
{
    use IsARealtor;

    public function markDownloaded(int $id): void
    {
        PromotionalMaterial::whereKey($id)->increment('downloads');

        $this->toast('Ready to send', 'Your link is already in it.', 'pos');
    }

    public function render()
    {
        $realtor = $this->realtor();

        $materials = PromotionalMaterial::where('active', true)
            ->with('property')
            ->orderBy('kind')->orderBy('title')
            ->get()
            ->map(fn (PromotionalMaterial $m) => [
                'model' => $m,
                // Personalised here rather than in the view, so the template
                // placeholders stay a property of the material and not of the
                // markup that happens to render it.
                //
                // Returned beside the model rather than set on it. An attribute
                // with no column behind it rides along into the next save() —
                // that is exactly how the narrator started writing a sort key
                // into the insights table and failing on "Unknown column".
                'personalised' => $m->personalise($realtor),
            ]);

        return view('livewire.realtor.materials', [
            'realtor' => $realtor,
            'materials' => $materials,
        ]);
    }
}
