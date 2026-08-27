<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Lead;
use App\Models\Property;
use App\Support\Fmt;
use App\Support\ImageStore;
use App\Support\Vocab;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Properties — the second hero module.
 *
 * The interesting column here is the demand index: inquiries per *available*
 * unit within a segment. It is what turns a listings table into inventory
 * intelligence, and it is what the demand-gap detector reads.
 */
#[Layout('layouts.app')]
#[Title('Properties')]
class Properties extends Component
{
    use InteractsWithEngine, WithFileUploads, WithPagination;

    /* --------------------------------------------------- the listing form */

    public bool $adding = false;

    public ?int $editing = null;

    public string $f_title = '';
    public string $f_type = 'Apartment';
    public string $f_location = '';
    public string $f_bedrooms = '';
    public string $f_size = '';
    public string $f_price = '';
    public string $f_commission = '5';
    public string $f_minMonths = '3';
    public string $f_maxMonths = '24';
    public string $f_description = '';
    public bool $f_listed = true;

    /** Newly picked files, not yet written anywhere. */
    public array $uploads = [];

    /** A URL typed into the box, for photography hosted elsewhere. */
    public string $imageUrl = '';

    /**
     * Images already on the listing, as stored — a mix of relative paths and
     * full URLs. Held in state so removals apply on save rather than
     * immediately, which is what makes cancelling a genuine cancel.
     */
    public array $images = [];

    public function addListing(): void
    {
        $this->resetListingForm();
        $this->adding = true;
    }

    public function editListing(int $id): void
    {
        $p = Property::findOrFail($id);

        $this->editing = $p->id;
        $this->f_title = (string) $p->title;
        $this->f_type = (string) $p->type;
        $this->f_location = (string) $p->location;
        $this->f_bedrooms = (string) ($p->bedrooms ?: '');
        $this->f_size = (string) ($p->size_sqm ?: '');
        $this->f_price = (string) $p->price;
        $this->f_commission = (string) round((float) $p->commission_rate * 100, 2);
        $this->f_minMonths = (string) ($p->min_duration_months ?: 3);
        $this->f_maxMonths = (string) ($p->max_duration_months ?: 24);
        $this->f_description = (string) $p->description;
        $this->f_listed = (bool) $p->listed;
        $this->images = array_values(array_filter((array) $p->images));
        $this->uploads = [];
        $this->imageUrl = '';
        $this->adding = true;
        $this->openId = null;
    }

    public function cancelListing(): void
    {
        $this->resetListingForm();
    }

    private function resetListingForm(): void
    {
        $this->reset([
            'adding', 'editing', 'f_title', 'f_location', 'f_bedrooms', 'f_size',
            'f_price', 'f_description', 'uploads', 'imageUrl', 'images',
        ]);
        $this->f_type = 'Apartment';
        $this->f_commission = '5';
        $this->f_minMonths = '3';
        $this->f_maxMonths = '24';
        $this->f_listed = true;
        $this->resetErrorBag();
    }

    /**
     * Add photography hosted somewhere else.
     *
     * Kept alongside upload rather than instead of it. An agency that already
     * has its stock shot and on a CDN should not have to re-upload 700 files
     * to use this product, and one that has a photo on a phone should not have
     * to find somewhere to host it first.
     */
    public function addImageUrl(): void
    {
        $url = trim($this->imageUrl);

        $this->validate(
            ['imageUrl' => ['required', 'url', 'max:2000']],
            attributes: ['imageUrl' => 'image link'],
        );

        if (! in_array($url, $this->images, true)) {
            $this->images[] = $url;
        }

        $this->imageUrl = '';
    }

    public function removeImage(int $index): void
    {
        if (! isset($this->images[$index])) {
            return;
        }

        // Only unlinked once the listing is saved — see saveListing().
        $this->images = array_values(array_diff_key($this->images, [$index => null]));
    }

    public function removeUpload(int $index): void
    {
        unset($this->uploads[$index]);
        $this->uploads = array_values($this->uploads);
    }

    /**
     * Write the listing.
     *
     * Files are moved into public/ only at this point. Doing it as they are
     * picked would leave orphans on disk every time somebody opened the form
     * and thought better of it.
     */
    public function saveListing(): void
    {
        $data = $this->validate([
            'f_title' => ['required', 'string', 'max:160'],
            'f_type' => ['required', 'string', 'max:60'],
            'f_location' => ['required', 'string', 'max:80'],
            'f_bedrooms' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'f_size' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'f_price' => ['required', 'numeric', 'min:1', 'max:1000000000000'],
            'f_commission' => ['required', 'numeric', 'min:0', 'max:15'],
            'f_minMonths' => ['required', 'numeric', 'min:1', 'max:60'],
            'f_maxMonths' => ['required', 'numeric', 'min:1', 'max:60', 'gte:f_minMonths'],
            'f_description' => ['nullable', 'string', 'max:4000'],
            'uploads.*' => ImageStore::imageRule(),
        ], attributes: [
            'f_title' => 'title', 'f_type' => 'type', 'f_location' => 'location',
            'f_bedrooms' => 'bedrooms', 'f_size' => 'size', 'f_price' => 'price',
            'f_commission' => 'commission rate', 'f_minMonths' => 'shortest term',
            'f_maxMonths' => 'longest term', 'uploads.*' => 'image',
        ]);

        $existing = $this->editing ? Property::findOrFail($this->editing) : null;

        $images = $this->images;
        foreach ($this->uploads as $file) {
            $images[] = ImageStore::put($file, 'img/properties');
        }

        $property = Property::updateOrCreate(
            ['id' => $this->editing],
            [
                'tenant_id' => $this->tenant()->id,
                'title' => $data['f_title'],
                'type' => $data['f_type'],
                'location' => $data['f_location'],
                'bedrooms' => (int) ($data['f_bedrooms'] ?: 0),
                'size_sqm' => (int) ($data['f_size'] ?: 0),
                'price' => (float) $data['f_price'],
                // Normalised rather than stored raw: this is the one figure a
                // typo turns into money owed to somebody.
                'commission_rate' => Property::normaliseRate($data['f_commission']),
                'min_duration_months' => (int) $data['f_minMonths'],
                'max_duration_months' => (int) $data['f_maxMonths'],
                'description' => $data['f_description'] ?: null,
                'images' => $images,
                'listed' => $this->f_listed,
            ] + ($existing ? [] : [
                // New listings only. An edit must never reset a unit's history
                // or knock it out of a commitment it is already part of.
                'ref' => $this->nextRef(),
                'status' => 'available',
                'listed_at' => now(),
                'days_on_market' => 0,
                'views' => 0,
                'inquiries' => 0,
            ]),
        );

        // Now that the row is written, remove the files it no longer points at.
        // In this order, so a failed save cannot delete a live photograph.
        foreach (array_diff((array) ($existing?->images ?? []), $images) as $dropped) {
            ImageStore::forget($dropped, 'img/properties');
        }

        $wasEditing = $existing !== null;
        $this->resetListingForm();
        $this->metrics()->flush();
        $this->dispatch('engine-changed');

        $this->toast(
            $wasEditing ? 'Listing updated' : 'Listing added',
            $property->ref.' · '.$property->title,
            'pos',
        );
    }

    /** Sequential reference, continuing whatever the seeder started. */
    private function nextRef(): string
    {
        $last = Property::query()->where('ref', 'like', 'NX-%')
            ->orderByRaw('CAST(SUBSTRING(ref, 4) AS UNSIGNED) DESC')
            ->value('ref');

        return 'NX-'.(((int) substr((string) $last, 3)) + 1);
    }

    #[Url(except: '')] public string $q = '';
    #[Url(except: '')] public string $location = '';
    #[Url(except: '')] public string $type = '';
    #[Url(except: '')] public string $status = '';
    #[Url(except: '')] public string $filter = '';        // stale
    #[Url(except: 'days_on_market')] public string $sort = 'days_on_market';
    #[Url(except: 'desc')] public string $dir = 'desc';

    public ?int $openId = null;

    public function updated($property): void
    {
        if (in_array($property, ['q', 'location', 'type', 'status', 'filter'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->dir = $this->dir === 'desc' ? 'asc' : 'desc';
        } else {
            $this->sort = $column;
            $this->dir = 'desc';
        }
    }

    public function toggleStale(): void
    {
        $this->filter = $this->filter === 'stale' ? '' : 'stale';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'location', 'type', 'status', 'filter');
        $this->resetPage();
    }

    public function open(int $id): void
    {
        $this->openId = $id;
    }

    public function closeDrawer(): void
    {
        $this->openId = null;
    }

    #[On('engine-changed')]
    public function refresh(): void
    {
        $this->metrics()->flush();
    }

    public function render()
    {
        $p = $this->metrics()->properties();
        $staleCut = $p['staleCut'];

        $rows = Property::query()
            ->when($this->q, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('ref', 'like', '%'.$this->q.'%')
                ->orWhere('title', 'like', '%'.$this->q.'%')
                ->orWhere('location', 'like', '%'.$this->q.'%')))
            ->when($this->location, fn ($qq) => $qq->where('location', $this->location))
            ->when($this->type, fn ($qq) => $qq->where('type', $this->type))
            ->when($this->status, fn ($qq) => $qq->where('status', $this->status))
            ->when($this->filter === 'stale', fn ($qq) => $qq
                ->where('status', 'available')
                ->where('days_on_market', '>', $staleCut))
            ->orderBy($this->sortColumn(), $this->dir)
            ->paginate(40);

        $detail = $this->openId ? Property::with('agent:id,name')->find($this->openId) : null;

        return view('livewire.pages.properties', [
            'p' => $p,
            'rows' => $rows,
            'staleCut' => $staleCut,
            'locations' => array_column(Vocab::LOCATIONS, 'name'),
            'types' => array_column(Vocab::PROP_TYPES, 'name'),
            'detail' => $detail,
            // Who is waiting for exactly this kind of unit. The drawer is the
            // one place where "we have stock" and "someone wants it" meet.
            'interested' => $detail
                ? Lead::where('is_open', true)
                    ->where('wants_location', $detail->location)
                    ->where('wants_type', $detail->type)
                    ->orderByDesc('score')->limit(8)->get()
                : collect(),
            'charts' => [
                'demand' => [
                    'data' => collect($p['segments'])->take(8)->map(fn ($s) => [
                        'label' => ($s['bedrooms'] ? $s['bedrooms'].'B ' : '')
                            .$this->shortType($s['type']).', '.$s['location'],
                        'value' => round($s['demandIndex'], 1),
                        'note' => Fmt::num($s['inquiries']).' inquiries · '
                            .Fmt::num($s['available']).' available',
                        // Orange where demand runs at more than twice the
                        // portfolio norm — the same line the detector reads.
                        'color' => $s['demandIndex'] > $p['baselineDemand'] * 2.2
                            ? 'var(--c2)' : 'var(--c1)',
                    ])->values()->all(),
                    'opts' => ['format' => 'number'],
                ],
                'dom' => [
                    'data' => collect($p['byLocation'])
                        ->filter(fn ($x) => $x['sold'] > 2)->take(8)
                        ->map(fn ($x) => [
                            'label' => $x['key'],
                            'value' => round($x['avgDom']),
                            'note' => Fmt::num($x['sold']).' sold',
                        ])->values()->all(),
                    'opts' => ['format' => 'count', 'height' => 210, 'color' => 'var(--c4)'],
                ],
            ],
        ]);
    }

    private function shortType(string $t): string
    {
        return str_replace(
            [' Duplex', 'Semi-Detached', 'Detached'],
            ['', 'Semi-D', 'Det'],
            $t,
        );
    }

    /** Whitelist — the sort key comes from the URL. */
    private function sortColumn(): string
    {
        return match ($this->sort) {
            'title' => 'title',
            'type' => 'type',
            'price' => 'price',
            'status' => 'status',
            'inquiries' => 'inquiries',
            default => 'days_on_market',
        };
    }
}
