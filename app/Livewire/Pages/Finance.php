<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\CommissionPayout;
use App\Models\Deal;
use App\Models\Payment;
use App\Platform\Instalments;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Finance — the third hero module.
 *
 * Receivables aging is the view that makes payment risk legible. The action
 * here is the most honest in the product: sending reminders does not conjure
 * payment, so SEND_REMINDERS models realistic recovery by age. Some
 * instalments clear, most do not, and the overdue figure moves by the amount
 * that actually cleared — not by the amount that was chased.
 */
#[Layout('layouts.app')]
#[Title('Finance')]
class Finance extends Component
{
    use InteractsWithEngine, WithPagination;

    /**
     * Aging buckets are ORDERED severity, not independent categories, so they
     * get an ordinal ramp climbing into the critical token — never the
     * categorical hues, which would imply these are unrelated groups.
     */
    public const AGING_COLORS = [
        '0-30' => 'var(--c1)',
        '31-60' => 'var(--sev-medium)',
        '61-90' => 'var(--sev-high)',
        '90+' => 'var(--sev-critical)',
    ];

    #[Url(except: '')] public string $q = '';
    #[Url(except: '')] public string $status = '';
    #[Url(except: '')] public string $filter = '';       // overdue

    /** Transfer reference typed against a row, keyed by instalment id. */
    public array $reference = [];

    public function mount(): void
    {
        if ($this->filter === 'overdue') {
            $this->status = 'overdue';
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['q', 'status'], true)) {
            $this->resetPage();
        }
    }

    /** Chase everything that is genuinely overdue. */
    public function chaseAll(): void
    {
        $f = $this->metrics()->finance();

        $this->run(
            'SEND_REMINDERS',
            ['filter' => 'overdue'],
            "Chase {$f['overdueCount']} overdue instalments",
            'finance:chase:'.now()->toDateString().':'.$f['overdueCount'],
        );
    }

    public function remindCustomer(int $customerId, string $name): void
    {
        $this->run(
            'SEND_REMINDERS',
            ['customerIds' => [$customerId]],
            "Remind {$name}",
            'finance:remind:'.$customerId.':'.now()->toDateString(),
        );
    }

    /**
     * Escalate the accounts where the relationship matters most.
     *
     * A VIP forty days late is a conversation, not a dunning letter — which is
     * exactly why this is a different action from the reminder sweep.
     */
    public function escalateHighValue(): void
    {
        $ids = collect($this->metrics()->finance()['byCustomer'])
            ->filter(fn ($c) => in_array($c['segment'], ['vip', 'high_value'], true))
            ->pluck('id')->all();

        if (! $ids) {
            $this->toast('Nothing to escalate', 'No high-value account is currently overdue.');

            return;
        }

        $this->run(
            'ESCALATE_ACCOUNTS',
            ['customerIds' => $ids],
            'Escalate '.count($ids).' high-value accounts',
            'finance:escalate:'.now()->toDateString().':'.count($ids),
        );
    }

    /* ------------------------------------------------- recording payments */

    /**
     * Record an instalment against an offline transfer.
     *
     * This is how money actually arrives: a buyer pays into the account and
     * somebody keys it in. The reference is asked for rather than optional,
     * because a disputed payment with no reference is a conversation nobody
     * can win.
     */
    public function recordPayment(int $paymentId): void
    {
        $reference = trim($this->reference[$paymentId] ?? "");

        if ($reference === "") {
            $this->toast("Reference needed", "Enter the transfer reference so this can be traced.");

            return;
        }

        $result = app(Instalments::class)->record(
            Payment::findOrFail($paymentId),
            auth()->user(),
            $reference,
        );

        $this->toast(
            $result["ok"] ? "Recorded" : "Not recorded",
            $result["message"],
            $result["ok"] ? "pos" : "neg",
        );

        if ($result["ok"]) {
            unset($this->reference[$paymentId]);
            $this->metrics()->flush();
            $this->dispatch("engine-changed");
        }
    }

    public function reversePayment(int $paymentId): void
    {
        $result = app(Instalments::class)->reverse(
            Payment::findOrFail($paymentId),
            auth()->user(),
        );

        $this->toast($result["ok"] ? "Reversed" : "Nothing to reverse", $result["message"]);

        if ($result["ok"]) {
            $this->metrics()->flush();
            $this->dispatch("engine-changed");
        }
    }

    /**
     * Pay a realtor what they are owed.
     *
     * Marks the accrual settled — it does not move money, which is done in a
     * bank. Recording it here is what lets Finance answer "paid" as distinct
     * from "owed" instead of treating an obligation as a transaction.
     */
    public function payCommission(int $payoutId): void
    {
        $payout = CommissionPayout::findOrFail($payoutId);

        if ($payout->isPaid()) {
            $this->toast("Already paid", "That payout was settled on "
                .Fmt::dateShort($payout->paid_at).".");

            return;
        }

        $payout->forceFill([
            "status" => "paid",
            "paid_at" => now(),
            "recorded_by" => auth()->id(),
        ])->save();

        $this->toast("Commission paid", Fmt::money($payout->amount)
            ." to ".($payout->realtor?->name ?? "realtor"), "pos");
    }
    private function run(string $type, array $payload, string $label, string $key): void
    {
        $result = $this->bus()->manual(
            type: $type, payload: $payload, label: $label, key: $key,
            userId: auth()->id(), area: 'finance',
        );

        $this->toast(
            $result['ok'] ? $label : 'Not applied',
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
        $f = $this->metrics()->finance();

        $rows = Payment::query()
            ->with('customer:id,name')
            ->when($this->q, fn ($qq) => $qq->whereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$this->q.'%')))
            // Filtered on the clock, the same way liveStatus() reads it — a
            // stored status column would disagree with the badge beside it.
            ->when($this->status === 'paid', fn ($qq) => $qq->whereNotNull('paid_at'))
            ->when($this->status === 'scheduled', fn ($qq) => $qq->whereNull('paid_at')->where('due_at', '>', now()))
            ->when($this->status === 'due', fn ($qq) => $qq->whereNull('paid_at')
                ->whereBetween('due_at', [now()->subDays(30), now()]))
            ->when($this->status === 'overdue', fn ($qq) => $qq->whereNull('paid_at')
                ->where('due_at', '<', now()->subDays(30)))
            ->orderByDesc('due_at')
            ->paginate(40);

        $agingTotal = array_sum($f['aging']) ?: 1;

        // Commission, split into what is owed and what has been paid. An
        // obligation and a transaction are different things, and one column
        // cannot honestly be both.
        $payouts = CommissionPayout::with(['realtor:id,name,code', 'commitment.property:id,title,ref'])
            ->orderByRaw("FIELD(status, 'owed', 'paid')")
            ->orderByDesc('created_at')
            ->limit(40)->get();

        return view('livewire.pages.finance', [
            'f' => $f,
            'rows' => $rows,
            'agingTotal' => $agingTotal,
            'charts' => [
                'collected' => [
                    'series' => [['name' => 'Collected', 'data' => $f['collectedSeries']]],
                    'opts' => ['format' => 'money', 'height' => 200],
                ],
            ],
            'hasVip' => collect($f['byCustomer'])
                ->contains(fn ($c) => in_array($c['segment'], ['vip', 'high_value'], true)),

            'payouts' => $payouts,
            'commissionOwed' => (float) CommissionPayout::where('status', 'owed')->sum('amount'),
            'commissionPaid' => (float) CommissionPayout::where('status', 'paid')->sum('amount'),

            // Total assets sold, which is the figure this screen is asked for
            // first and could not previously answer.
            'assetsSold' => (int) Deal::whereIn('status', array_merge(Deal::LIVE, ['completed']))->count(),
            'assetsSoldValue' => (float) Deal::whereIn('status', array_merge(Deal::LIVE, ['completed']))->sum('value'),
        ]);
    }
}
