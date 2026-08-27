<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A commitment.
 *
 * An investor picks a property and a duration; the row opens as `pending` and
 * the unit is held, not sold. An admin approves it, and only then does the
 * instalment plan exist.
 *
 * `won` is untouched and still means "this one closed" — the sales metrics and
 * several detectors read it. `status` carries the new lifecycle beside it.
 */
class Deal extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime', 'closed_at' => 'datetime',
        'committed_at' => 'datetime', 'approved_at' => 'datetime',
        'value' => 'float', 'commission' => 'float', 'commission_rate' => 'float',
        'won' => 'bool', 'duration_months' => 'int',
    ];

    public const STATUSES = [
        'pending' => 'Awaiting approval',
        'approved' => 'Approved',
        'active' => 'Paying',
        'completed' => 'Completed',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses where the investor still owes money. */
    public const LIVE = ['approved', 'active'];

    /* ------------------------------------------------------- relationships */

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }

    /** The same relation, in the product's language. */
    public function realtor(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    public function investor(): BelongsTo { return $this->belongsTo(Customer::class, 'customer_id'); }

    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function payments(): HasMany { return $this->hasMany(Payment::class); }

    /** The instalment journey, ordered the way it is read. */
    public function instalments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('instalment');
    }

    public function payout(): HasOne { return $this->hasOne(CommissionPayout::class); }

    public function feedback(): HasMany { return $this->hasMany(Feedback::class); }

    /* -------------------------------------------------------------- scopes */

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereIn('status', self::LIVE);
    }

    /* --------------------------------------------------------------- state */

    public function isPending(): bool { return $this->status === 'pending'; }

    public function isLive(): bool { return in_array($this->status, self::LIVE, true); }

    public function isCompleted(): bool { return $this->status === 'completed'; }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? 'Unknown';
    }

    /**
     * Whether this is allowed to be approved.
     *
     * The realtor check is the important one. Approval is what generates the
     * plan and freezes the commission, so approving a commitment whose investor
     * has nobody attached creates an earned commission with no owner — and
     * nothing later in the system can work out who it belonged to.
     */
    public function approvalBlocker(): ?string
    {
        if (! $this->isPending()) {
            return 'This commitment has already been decided.';
        }

        if (! $this->customer?->isAssigned()) {
            return 'Assign a realtor to this investor first — commission has to have an owner.';
        }

        if (! $this->duration_months) {
            return 'This commitment has no instalment duration.';
        }

        if (! $this->property) {
            return 'The property behind this commitment no longer exists.';
        }

        return null;
    }

    public function canBeApproved(): bool
    {
        return $this->approvalBlocker() === null;
    }

    /* ---------------------------------------------------------- the journey */

    public function paidCount(): int
    {
        return $this->instalments->filter(fn (Payment $p) => $p->isSettled())->count();
    }

    public function paidAmount(): float
    {
        return (float) $this->instalments
            ->filter(fn (Payment $p) => $p->isSettled())->sum('amount');
    }

    public function outstanding(): float
    {
        return max(0.0, (float) $this->value - $this->paidAmount());
    }

    /** 0..1, by money rather than by count — a final balloon payment matters. */
    public function progress(): float
    {
        return $this->value > 0 ? min(1.0, $this->paidAmount() / (float) $this->value) : 0.0;
    }

    /** The earliest instalment still unpaid. What "next" means to a buyer. */
    public function nextInstalment(): ?Payment
    {
        return $this->instalments->first(fn (Payment $p) => ! $p->isSettled());
    }

    /* ----------------------------------------------------------- commission */

    /**
     * Commission from the FROZEN rate, never from the property.
     *
     * The rate was copied onto this row when it was approved because it is a
     * contractual figure. Reading it live off the property means editing a
     * listing next year silently rewrites commissions already earned — the
     * exact mirror of the payment-status rule, where the stored value is the
     * one that must never be trusted.
     */
    public function commissionDue(): float
    {
        return (float) $this->value * (float) ($this->commission_rate ?? 0);
    }
}
