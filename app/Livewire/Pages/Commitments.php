<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Agent;
use App\Models\Deal;
use App\Platform\Commitments as Service;
use App\Platform\Realtors as RealtorService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The pending queue.
 *
 * Approving is what generates the instalment plan and freezes the commission,
 * so this screen refuses to let it happen while the investor has no realtor —
 * and offers the fix inline rather than sending someone to another page to
 * find it. Two clicks in one place beats a correct error message.
 */
#[Layout('layouts.app')]
#[Title('Commitments')]
class Commitments extends Component
{
    use InteractsWithEngine;

    #[Url(as: 'show', keep: false)]
    public string $filter = 'pending';

    /** Free-text over investor, property and reference. */
    #[Url(as: 'q', keep: false)]
    public string $search = '';

    /** The commitment open in the side canvas. */
    public ?int $openId = null;

    /**
     * Realtor picked in the assign control, keyed by commitment.
     *
     * Still keyed rather than a single value because the drawer is not the
     * only way in — leaving it per-commitment means a half-finished choice
     * cannot leak onto a different row when the drawer is reopened.
     */
    public array $assignTo = [];

    /**
     * Typed into the realtor picker.
     *
     * A `<select>` of 118 approved realtors is a control nobody can use: the
     * name you want is somewhere in a scroll list with no way to jump to it,
     * and the code — which is what a person actually has written down — is not
     * searchable at all. This filters on both.
     */
    public string $realtorSearch = '';

    public function open(int $id): void
    {
        $this->openId = $id;
        $this->realtorSearch = '';
    }

    public function closeDrawer(): void
    {
        $this->openId = null;
        $this->realtorSearch = '';
    }

    public function updatedSearch(): void
    {
        $this->openId = null;
    }

    public function approve(int $id): void
    {
        $result = app(Service::class)->approve(
            Deal::with(['customer', 'property'])->findOrFail($id),
            auth()->user(),
        );

        $this->toast(
            $result['ok'] ? 'Approved' : 'Cannot approve yet',
            $result['message'],
            $result['ok'] ? 'pos' : 'neg',
        );

        // Closed on success only. A refusal has a reason the operator needs to
        // read and act on, and it is written on the panel that would close.
        if ($result['ok']) {
            $this->closeDrawer();
        }

        $this->dispatch('engine-changed');
    }

    public function decline(int $id): void
    {
        $result = app(Service::class)->decline(Deal::findOrFail($id), auth()->user());

        $this->toast($result['ok'] ? 'Declined' : 'Nothing to do', $result['message']);

        if ($result['ok']) {
            $this->closeDrawer();
            $this->dispatch('engine-changed');
        }
    }

    /**
     * Cancel a live commitment.
     *
     * Distinct from declining: a plan exists, money may have been received, and
     * commission may already have been paid out. The result says so plainly
     * rather than reporting a clean cancellation.
     */
    public function cancel(int $id): void
    {
        $result = app(Service::class)->cancel(
            Deal::with(['property', 'payout'])->findOrFail($id),
            auth()->user(),
        );

        $this->toast(
            $result['ok'] ? 'Cancelled' : 'Cannot cancel',
            $result['message'],
            $result['ok'] && ($result['clawback'] ?? 0) > 0 ? 'neg' : ($result['ok'] ? '' : 'neg'),
        );

        if ($result['ok']) {
            $this->closeDrawer();
        }

        $this->dispatch('engine-changed');
    }

    /** Clear the blocker without leaving the queue. */
    public function assign(int $commitmentId): void
    {
        $commitment = Deal::with('customer')->findOrFail($commitmentId);
        $realtorId = (int) ($this->assignTo[$commitmentId] ?? 0);

        if (! $realtorId) {
            $this->toast('Pick a realtor', 'Choose who brought this investor in.');

            return;
        }

        $result = app(RealtorService::class)->assign(
            $commitment->customer,
            Agent::findOrFail($realtorId),
            auth()->user(),
        );

        $this->toast($result['ok'] ? 'Assigned' : 'Nothing to do', $result['message'],
            $result['ok'] ? 'pos' : '');

        if ($result['ok']) {
            // The blocker is gone; the drawer has to redraw with Approve
            // enabled rather than leave the operator looking at a stale
            // warning telling them to do the thing they just did.
            $this->realtorSearch = '';
            unset($this->assignTo[$commitmentId]);
            $this->dispatch('engine-changed');
        }
    }

    /**
     * Approved realtors matching what has been typed.
     *
     * Capped at 8. The list is an aid to finding a known person, not a
     * directory to browse — if the search is too loose to narrow 118 people
     * to eight, the answer is a better search term, not a longer list.
     */
    private function realtorOptions()
    {
        return Agent::query()
            ->approved()
            ->when($this->realtorSearch, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'code']);
    }

    public function render()
    {
        $base = fn () => Deal::query()->whereNotNull('committed_at');

        $rows = $base()
            ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%"))
                ->orWhereHas('property', fn ($p) => $p
                    ->where('title', 'like', "%{$s}%")
                    ->orWhere('ref', 'like', "%{$s}%"))))
            // instalments is loaded because every live and settled row draws a
            // progress figure — progress(), paidCount() and the instalment
            // count all read the relation. Without it this page issues one
            // extra query per row: sixty on a single load.
            ->with(['customer.realtor', 'property', 'realtor', 'instalments'])
            ->orderByDesc('committed_at')
            ->limit(60)->get();

        // Loaded separately rather than plucked from $rows: the drawer has to
        // keep working when a filter change drops its row out of the table.
        $detail = $this->openId
            ? Deal::with(['customer.realtor', 'property', 'realtor', 'instalments', 'approver', 'payout'])
                ->find($this->openId)
            : null;

        return view('livewire.pages.commitments', [
            'rows' => $rows,
            'detail' => $detail,
            'blocker' => $detail?->isPending() ? $detail->approvalBlocker() : null,
            'realtorOptions' => $detail && ! $detail->customer?->isAssigned()
                ? $this->realtorOptions()
                : collect(),
            'counts' => [
                'pending' => (clone $base())->where('status', 'pending')->count(),
                'active' => (clone $base())->whereIn('status', Deal::LIVE)->count(),
                'completed' => (clone $base())->where('status', 'completed')->count(),
                'all' => (clone $base())->count(),
            ],
        ]);
    }
}
