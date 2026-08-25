<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * A buyer's file, assembled once per request.
 *
 * The portal reads the SAME rows the console does. That is the point: the
 * buyer's payment schedule and the agency's receivables view are two
 * renderings of one set of records, so they can never disagree with each
 * other. There is no export, no sync, and no second copy to drift.
 */
class Purchase
{
    public readonly ?\App\Models\Deal $deal;

    public readonly ?\App\Models\Property $property;

    public readonly ?\App\Models\Agent $agent;

    /** @var Collection<int, Payment> */
    public readonly Collection $payments;

    public function __construct(public readonly Customer $customer)
    {
        // The largest won deal — a customer with two purchases is looking at
        // the one that matters, not whichever the database returned first.
        $this->deal = $customer->deals()
            ->with('property')
            ->where('won', true)
            ->orderByDesc('value')
            ->first();

        $this->property = $this->deal?->property;
        $this->agent = $customer->agent;

        $this->payments = $this->deal
            ? Payment::where('deal_id', $this->deal->id)->orderBy('instalment')->get()
            : collect();
    }

    public static function for(Customer $customer): self
    {
        return new self($customer);
    }

    /* --------------------------------------------------------- groupings */

    public function paid(): Collection
    {
        return $this->payments->filter(fn ($p) => $p->liveStatus() === 'paid')->values();
    }

    public function overdue(): Collection
    {
        return $this->payments->filter(fn ($p) => $p->liveStatus() === 'overdue')->values();
    }

    public function due(): Collection
    {
        return $this->payments->filter(fn ($p) => $p->liveStatus() === 'due')->values();
    }

    public function upcoming(): Collection
    {
        return $this->payments->filter(fn ($p) => $p->liveStatus() === 'scheduled')
            ->sortBy('due_at')->values();
    }

    /**
     * "Next" is simply the earliest unpaid instalment — full stop.
     *
     * Bucketing it as overdue-then-upcoming skips anything past due but not yet
     * 30 days late, and the portal ends up telling someone instalment 5 is next
     * while 4 sits unpaid. That is exactly the kind of quiet wrongness that
     * costs trust in a payments screen.
     */
    public function next(): ?Payment
    {
        return $this->payments->filter(fn ($p) => $p->liveStatus() !== 'paid')
            ->sortBy('due_at')->first();
    }

    /* ----------------------------------------------------------- figures */

    /** Anything past its due date, however slightly. */
    public function lateCount(): int
    {
        return $this->overdue()->count() + $this->due()->count();
    }

    public function lateAmount(): float
    {
        return (float) $this->overdue()->sum('amount') + (float) $this->due()->sum('amount');
    }

    public function worstLateBy(): int
    {
        return (int) $this->overdue()->concat($this->due())->max(fn ($p) => $p->lateBy());
    }

    public function total(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function paidAmount(): float
    {
        return (float) $this->paid()->sum('amount');
    }

    public function outstanding(): float
    {
        return $this->total() - $this->paidAmount();
    }

    public function progress(): float
    {
        return $this->total() > 0 ? $this->paidAmount() / $this->total() : 0.0;
    }

    public function settled(): bool
    {
        return $this->payments->isNotEmpty() && $this->paid()->count() === $this->payments->count();
    }

    public function reference(): string
    {
        return $this->deal ? 'NX-'.str_pad((string) $this->deal->id, 5, '0', STR_PAD_LEFT) : '—';
    }

    /* ------------------------------------------------------------ picker */

    /**
     * Whose account to show when a demo account is not tied to one customer.
     *
     * Deliberately NOT random: choose the file most worth looking at — a
     * multi-instalment plan, partly paid, still running. A portal that opens on
     * a fully settled account shows nothing.
     */
    public static function mostInteresting(): ?Customer
    {
        return Customer::query()
            ->withCount([
                'payments',
                'payments as paid_count' => fn ($q) => $q->whereNotNull('paid_at'),
                'payments as ahead_count' => fn ($q) => $q->whereNull('paid_at')->where('due_at', '>', now()),
            ])
            ->having('payments_count', '>=', 3)
            ->having('paid_count', '>', 0)
            ->having('ahead_count', '>', 0)
            ->orderByDesc('ltv')
            ->first()
            ?? Customer::orderByDesc('ltv')->first();
    }
}
