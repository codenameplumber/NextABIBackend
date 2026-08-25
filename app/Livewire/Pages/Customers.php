<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Customer;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Customers — lifetime value, segments and satisfaction.
 *
 * Satisfaction sits directly beside value on purpose. A detractor worth
 * ₦400M is a different problem from a detractor worth ₦4M, and a table that
 * separates the two columns onto different screens hides that.
 */
#[Layout('layouts.app')]
#[Title('Customers')]
class Customers extends Component
{
    use InteractsWithEngine, WithPagination;

    #[Url(except: '')] public string $q = '';
    #[Url(except: '')] public string $segment = '';
    #[Url(except: '')] public string $filter = '';       // detractors
    #[Url(except: 'ltv')] public string $sort = 'ltv';
    #[Url(except: 'desc')] public string $dir = 'desc';

    public ?int $openId = null;

    public function updated($property): void
    {
        if (in_array($property, ['q', 'segment', 'filter'], true)) {
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

    public function toggleDetractors(): void
    {
        $this->filter = $this->filter === 'detractors' ? '' : 'detractors';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'segment', 'filter');
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
        $c = $this->metrics()->customers();

        $rows = Customer::query()
            ->withCount(['deals as won_deals' => fn ($q) => $q->where('won', true)])
            ->when($this->q, fn ($qq) => $qq->where('name', 'like', '%'.$this->q.'%'))
            ->when($this->segment, fn ($qq) => $qq->where('segment', $this->segment))
            ->when($this->filter === 'detractors', fn ($qq) => $qq
                ->whereNotNull('satisfaction')->where('satisfaction', '<=', 2.5))
            ->orderBy($this->sortColumn(), $this->dir)
            ->paginate(40);

        $detail = $this->openId
            ? Customer::with(['agent:id,name', 'deals.property:id,title', 'feedback'])->find($this->openId)
            : null;

        $top = Customer::orderByDesc('ltv')->limit(8)
            ->withCount(['deals as won_deals' => fn ($q) => $q->where('won', true)])
            ->get();

        return view('livewire.pages.customers', [
            'c' => $c,
            'rows' => $rows,
            'detail' => $detail,
            'overdue' => $detail
                ? $detail->payments()->whereNull('paid_at')
                    ->where('due_at', '<', now()->subDays(30))->count()
                : 0,
            'charts' => [
                'segments' => [
                    'data' => collect($c['bySegment'])->map(fn ($s) => [
                        'label' => Fmt::titleCase($s['key']),
                        'value' => $s['ltv'],
                    ])->values()->all(),
                    'opts' => ['format' => 'money', 'centerLabel' => 'Total LTV', 'size' => 200],
                ],
                'top' => [
                    'data' => $top->map(fn ($x) => [
                        'label' => $x->name,
                        'value' => (float) $x->ltv,
                        'note' => $x->won_deals.' deal'.($x->won_deals === 1 ? '' : 's')
                            .' · '.Fmt::titleCase($x->segment),
                    ])->all(),
                    'opts' => ['format' => 'money'],
                ],
            ],
        ]);
    }

    private function sortColumn(): string
    {
        return match ($this->sort) {
            'name' => 'name',
            'segment' => 'segment',
            'satisfaction' => 'satisfaction',
            'since' => 'since',
            default => 'ltv',
        };
    }
}
