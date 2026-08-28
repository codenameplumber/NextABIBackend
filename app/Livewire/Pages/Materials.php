<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Property;
use App\Models\PromotionalMaterial;
use App\Support\ImageStore;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Promotional materials, from the admin side.
 *
 * One asset is written here and every realtor downloads their own version of
 * it. Most of them are ARTWORK — a flyer for a launch, a square for Instagram,
 * a brochure PDF — and the screen is built around that: a grid of what the
 * things actually look like, because a table of filenames is unusable for
 * picking which flyer to send.
 *
 * Message templates are still here, as one kind among several rather than the
 * only one. Their placeholders are listed on the form rather than documented
 * elsewhere, because a typo'd placeholder ships "call me on {phone}" to a few
 * hundred prospects.
 */
#[Layout('layouts.app')]
#[Title('Promotional materials')]
class Materials extends Component
{
    use InteractsWithEngine, WithFileUploads;

    public bool $adding = false;

    public ?int $editing = null;

    public string $title = '';
    public string $kind = 'flyer';
    public string $body = '';
    public ?int $property_id = null;

    /** A newly chosen file, not yet written anywhere. */
    public $asset = null;

    /** What is already on the record, so an edit without a new file keeps it. */
    public ?string $existingFile = null;

    public function add(): void
    {
        $this->resetForm();
        $this->adding = true;
    }

    public function edit(int $id): void
    {
        $m = PromotionalMaterial::findOrFail($id);

        $this->editing = $m->id;
        $this->title = $m->title;
        $this->kind = $m->kind;
        $this->body = (string) $m->body;
        $this->property_id = $m->property_id;
        $this->existingFile = $m->file_path;
        $this->asset = null;
        $this->adding = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['adding', 'editing', 'title', 'body', 'property_id', 'asset', 'existingFile']);
        $this->kind = 'flyer';
        $this->resetErrorBag();
    }

    private function isVisual(): bool
    {
        return in_array($this->kind, PromotionalMaterial::VISUAL_KINDS, true);
    }

    /**
     * Save, with the requirements decided by the kind.
     *
     * A flyer needs artwork; a message template needs words. Demanding both of
     * everything is how a form gets abandoned, and demanding neither is how a
     * "flyer" with no file ends up on a realtor's screen as a broken image.
     */
    public function save(): void
    {
        $visual = $this->isVisual();

        $rules = [
            'title' => ['required', 'string', 'max:140'],
            'kind' => ['required', 'string', 'in:'.implode(',', array_keys(PromotionalMaterial::KINDS))],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'body' => [$visual ? 'nullable' : 'required', 'string', 'max:4000'],
        ];

        // On an edit the file is already there, so a new one is optional.
        $rules['asset'] = $visual && ! $this->existingFile
            ? ['required', ...ImageStore::assetRule()]
            : ['nullable', ...ImageStore::assetRule()];

        $data = $this->validate($rules, attributes: [
            'asset' => 'file',
            'body' => $visual ? 'caption' : 'message',
        ]);

        $path = $this->existingFile;

        if ($this->asset) {
            $path = ImageStore::put($this->asset, 'downloads/materials');
        }

        $material = PromotionalMaterial::updateOrCreate(
            ['id' => $this->editing],
            [
                'tenant_id' => $this->tenant()->id,
                'title' => $data['title'],
                'kind' => $data['kind'],
                'body' => $data['body'] ?: null,
                'property_id' => $data['property_id'],
                'file_path' => $path,
                'active' => true,
                'created_by' => auth()->id(),
            ],
        );

        // Replaced artwork is removed only after the row points at the new
        // file, so a failed save can never leave a record with no asset.
        if ($this->asset && $this->existingFile && $this->existingFile !== $path) {
            ImageStore::forget($this->existingFile, 'downloads/materials');
        }

        $wasEditing = $this->editing !== null;
        $this->resetForm();

        $this->toast(
            $wasEditing ? 'Updated' : 'Published',
            $material->title.' — every realtor sees it with their own details in it.',
            'pos',
        );
    }

    public function togglePublished(int $id): void
    {
        $m = PromotionalMaterial::findOrFail($id);
        $m->forceFill(['active' => ! $m->active])->save();

        $this->toast($m->active ? 'Published' : 'Withdrawn', $m->title);
    }

    /**
     * Delete a material and the file behind it.
     *
     * The file goes too. A withdrawn material can be republished, but a
     * deleted one cannot, and leaving its artwork on disk means a folder that
     * grows forever with assets nothing references.
     */
    public function destroy(int $id): void
    {
        $m = PromotionalMaterial::findOrFail($id);
        $path = $m->file_path;
        $title = $m->title;

        $m->delete();
        ImageStore::forget($path, 'downloads/materials');

        $this->toast('Deleted', $title.' is gone, along with its file.');
    }

    public function render()
    {
        return view('livewire.pages.materials', [
            'rows' => PromotionalMaterial::with('property')
                ->orderByDesc('active')
                ->orderByDesc('created_at')->get(),
            'properties' => Property::query()->onMarket()
                ->orderBy('title')->limit(200)->get(['id', 'title', 'ref']),
            'kinds' => PromotionalMaterial::KINDS,
            'needsFile' => $this->isVisual(),
        ]);
    }
}
