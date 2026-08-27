<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'due_at' => 'datetime', 'paid_at' => 'datetime', 'reminded_at' => 'datetime',
        'original_due_at' => 'datetime', 'amount' => 'float', 'restructured' => 'bool',
        'recorded_at' => 'datetime',
    ];

    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    public function investor(): BelongsTo { return $this->belongsTo(Customer::class, 'customer_id'); }

    public function commitment(): BelongsTo { return $this->belongsTo(Deal::class, 'deal_id'); }

    /** Who keyed it in. An offline transfer with no recorder cannot be disputed. */
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    /**
     * Status derived from the clock, not read from the stored column.
     *
     * The stored value is a snapshot from whenever the row was last written.
     * Trust it and an instalment three weeks past due still reports itself as
     * scheduled, which is the kind of quiet wrongness that permanently costs a
     * customer's confidence in a payments screen.
     */
    public function liveStatus(): string
    {
        if ($this->paid_at || $this->status === 'paid') {
            return 'paid';
        }

        if ($this->due_at->isFuture()) {
            return 'scheduled';
        }

        return $this->due_at->diffInDays(now()) > 30 ? 'overdue' : 'due';
    }

    public function lateBy(): int
    {
        if ($this->liveStatus() === 'paid' || $this->due_at->isFuture()) {
            return 0;
        }

        return (int) $this->due_at->diffInDays(now());
    }

    public function isSettled(): bool { return $this->liveStatus() === 'paid'; }

    public function isLate(): bool { return in_array($this->liveStatus(), ['overdue', 'due'], true); }

    /** Label for the investor's progress table. */
    public function statusLabel(): string
    {
        return [
            'paid' => 'Paid',
            'due' => 'Due now',
            'overdue' => 'Overdue',
            'scheduled' => 'Scheduled',
        ][$this->liveStatus()] ?? 'Scheduled';
    }
}
