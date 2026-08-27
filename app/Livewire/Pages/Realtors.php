<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Agent;
use App\Models\CommissionPayout;
use App\Platform\Realtors as Service;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Realtors — approving them, and seeing what they produce.
 *
 * The approval queue is first because it is the thing that blocks somebody
 * else's work. A realtor sitting unapproved for three days is three days of
 * leads nobody captured.
 */
#[Layout('layouts.app')]
#[Title('Realtors')]
class Realtors extends Component
{
    use InteractsWithEngine;

    /**
     * Opens on everyone, not on the approval queue.
     *
     * Defaulting to `pending` meant a 120-realtor book opened showing two
     * people, and the screen read as an empty product. The queue has not lost
     * its priority — the ordering below still floats pending to the top, and
     * the sidebar carries the count — but the page's job is the roster.
     */
    #[Url(as: 'show', keep: false)]
    public string $filter = 'all';

    #[Url(as: 'q', keep: false)]
    public string $search = '';

    #[Url(except: 'created_at')] public string $sort = 'created_at';
    #[Url(except: 'desc')] public string $dir = 'desc';

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->dir = $this->dir === 'desc' ? 'asc' : 'desc';

            return;
        }

        $this->sort = $column;
        // Names read best A-Z; every other column is interesting at its top.
        $this->dir = $column === 'name' ? 'asc' : 'desc';
    }

    /** Whitelist — the sort key arrives from the URL. */
    private function sortColumn(): string
    {
        return match ($this->sort) {
            'name' => 'name',
            'code' => 'code',
            'status' => 'status',
            'investors' => 'investors_count',
            'leads' => 'leads_count',
            default => 'created_at',
        };
    }

    public function approve(int $id): void
    {
        $result = app(Service::class)->approve(Agent::findOrFail($id), auth()->user());

        $this->toast(
            $result['ok'] ? 'Approved' : 'Nothing to do',
            $result['message'],
            $result['ok'] ? 'pos' : '',
        );
    }

    public function suspend(int $id): void
    {
        $result = app(Service::class)->suspend(Agent::findOrFail($id), auth()->user());

        $this->toast($result['ok'] ? 'Suspended' : 'Nothing to do', $result['message']);
    }

    public function render()
    {
        $base = fn () => Agent::query()
            ->when($this->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")));

        $rows = $base()
            ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->withCount(['investors', 'leads'])
            // Pending always floats, whatever the sort. Somebody waiting on
            // approval is blocking their own work, and burying them under a
            // column sort is how an application sits for three days.
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'suspended')")
            ->orderBy($this->sortColumn(), $this->dir === 'asc' ? 'asc' : 'desc')
            ->limit(200)->get();

        // Owed and paid per realtor, in two aggregates rather than a query per
        // row — this table runs to a hundred people.
        $payouts = CommissionPayout::query()
            ->selectRaw('agent_id, SUM(amount) total, '
                ."SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) paid")
            ->groupBy('agent_id')->get()->keyBy('agent_id');

        return view('livewire.pages.realtors', [
            'rows' => $rows,
            'payouts' => $payouts,
            'counts' => [
                'pending' => (clone $base())->where('status', 'pending')->count(),
                'approved' => (clone $base())->where('status', 'approved')->count(),
                'suspended' => (clone $base())->where('status', 'suspended')->count(),
                'all' => (clone $base())->count(),
            ],
        ]);
    }
}
