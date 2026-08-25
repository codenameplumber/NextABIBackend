<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Activity;
use App\Models\Lead;
use App\Support\Vocab;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Leads — the hero module.
 *
 * Every drill target from a lead finding lands here with query parameters
 * pre-applied (?filter=stale&sort=budget), which is what makes the evidence
 * chips on an insight card feel like part of one system rather than a link.
 */
#[Layout('layouts.app')]
#[Title('Leads')]
class Leads extends Component
{
    use InteractsWithEngine, WithPagination;

    #[Url(except: '')] public string $q = '';
    #[Url(except: '')] public string $status = '';
    #[Url(except: '')] public string $source = '';
    #[Url(except: '')] public string $filter = '';       // stale | hot-idle
    #[Url(except: 'score')] public string $sort = 'score';
    #[Url(except: 'desc')] public string $dir = 'desc';

    /** The lead open in the detail drawer. */
    public ?int $openId = null;

    /**
     * Detectors drill in with `sort=oldest`, meaning "the ones neglected
     * longest first". That is ascending on last contact, so translate it here
     * rather than leaving the caller to know which way round the column runs.
     */
    public function mount(): void
    {
        if ($this->sort === 'oldest') {
            $this->sort = 'last_contact_at';
            $this->dir = 'asc';
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['q', 'status', 'source', 'filter'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->dir = $this->dir === 'desc' ? 'asc' : 'desc';
        } else {
            $this->sort = $column;
            $this->dir = in_array($column, ['name', 'last_contact_at'], true) ? 'asc' : 'desc';
        }
    }

    public function toggleFilter(string $value): void
    {
        $this->filter = $this->filter === $value ? '' : $value;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'status', 'source', 'filter');
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

    /**
     * Sweep the whole stale backlog.
     *
     * Deliberately not a bespoke code path: it goes through the action bus
     * like everything else, so it is logged, it is idempotent, and the stale
     * detector re-runs afterwards and takes its own finding down.
     */
    public function sweepStale(): void
    {
        $count = $this->metrics()->leads()['stale'];

        $result = $this->bus()->manual(
            type: 'SCHEDULE_FOLLOWUP',
            payload: ['filter' => 'stale', 'note' => 'Manual sweep from the Leads page'],
            label: "Follow up {$count} stale leads",
            // Keyed to the day and the size of the backlog, so a double-click
            // is refused but tomorrow's genuine sweep is not.
            key: 'leads:sweep:'.now()->toDateString().':'.$count,
            userId: auth()->id(),
            area: 'leads',
        );

        $this->toast(
            $result['ok'] ? 'Follow-ups scheduled' : 'Not applied',
            $result['message'],
            $result['ok'] ? 'pos' : 'neg',
        );

        if ($result['ok']) {
            $this->metrics()->flush();
            $this->dispatch('engine-changed');
        }
    }

    public function followUp(int $leadId): void
    {
        $lead = Lead::find($leadId);

        if (! $lead) {
            return;
        }

        $result = $this->bus()->manual(
            type: 'SCHEDULE_FOLLOWUP',
            payload: ['ids' => [$lead->id], 'note' => 'Individual follow-up'],
            label: "Follow up {$lead->name}",
            key: 'lead:'.$lead->id.':'.now()->toDateString(),
            userId: auth()->id(),
            area: 'leads',
        );

        $this->toast(
            $result['ok'] ? 'Follow-up scheduled' : 'Not applied',
            $result['message'],
            $result['ok'] ? 'pos' : 'neg',
        );

        if ($result['ok']) {
            $this->metrics()->flush();
            $this->dispatch('engine-changed');
        }
    }

    #[On('engine-changed')]
    public function refresh(): void
    {
        $this->metrics()->flush();
    }

    public function render()
    {
        $m = $this->metrics();
        $leads = $m->leads(90);
        $funnel = $m->funnel();

        $rows = Lead::query()
            ->with('agent:id,name')
            ->when($this->q, fn ($qq) => $qq->where('name', 'like', '%'.$this->q.'%'))
            ->when($this->status, fn ($qq) => $qq->where('status', $this->status))
            ->when($this->source, fn ($qq) => $qq->where('source', $this->source))
            ->when($this->filter === 'stale', fn ($qq) => $qq
                ->where('is_open', true)
                ->where('last_contact_at', '<=', now()->subDays(14)))
            ->when($this->filter === 'hot-idle', fn ($qq) => $qq
                ->where('is_open', true)
                ->where('score', '>=', 70))
            ->orderBy($this->sortColumn(), $this->dir)
            ->paginate(40);

        return view('livewire.pages.leads', [
            'leads' => $leads,
            'rows' => $rows,
            'sources' => array_column(Vocab::SOURCES, 'name'),
            'stages' => Vocab::STAGES,
            'charts' => [
                'conversion' => [
                    'data' => collect($leads['bySource'])
                        ->sortByDesc('conversion')->take(8)
                        ->map(fn ($s) => [
                            'label' => $s['key'],
                            'value' => $s['conversion'],
                            'note' => number_format($s['leads']).' leads · '
                                .number_format($s['won']).' closed',
                        ])->values()->all(),
                    'opts' => ['format' => 'pct'],
                ],
                'funnel' => ['stages' => $funnel['stages']],
            ],
            'detail' => $this->openId
                ? Lead::with(['agent:id,name', 'property:id,title,location'])->find($this->openId)
                : null,
            'timeline' => $this->openId
                ? Activity::where('entity_type', 'lead')
                    ->where('entity_id', $this->openId)
                    ->orderByDesc('at')->limit(30)->get()
                : collect(),
        ]);
    }

    /** Whitelist — the sort key comes from the URL. */
    private function sortColumn(): string
    {
        return match ($this->sort) {
            'name' => 'name',
            'budget' => 'budget',
            'status' => 'status',
            'source' => 'source',
            'oldest', 'last_contact_at' => 'last_contact_at',
            default => 'score',
        };
    }
}
