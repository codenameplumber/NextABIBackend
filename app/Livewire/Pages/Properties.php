<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Lead;
use App\Models\Property;
use App\Support\Fmt;
use App\Support\Vocab;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
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
    use InteractsWithEngine, WithPagination;

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
