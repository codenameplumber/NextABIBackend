<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a realtor has earned, and whether it has actually been paid.
 *
 * Kept apart from the commitment so Finance can answer "paid" as distinct from
 * "owed" — one figure is an obligation and the other is a transaction, and a
 * single column cannot honestly be both.
 *
 * The rate is carried here as well as on the commitment. Duplication on
 * purpose: this row is the record of a payment made at a rate, and it has to
 * stay readable even if the commitment is later cancelled.
 */
class CommissionPayout extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float', 'rate' => 'float', 'paid_at' => 'datetime',
    ];

    public function realtor(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }

    public function commitment(): BelongsTo { return $this->belongsTo(Deal::class, 'deal_id'); }

    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    public function isPaid(): bool { return $this->status === 'paid'; }

    public function isWrittenOff(): bool { return $this->status === 'written_off'; }

    /**
     * Written off is not the same as owed, and not the same as paid.
     *
     * It is what a cancelled sale leaves behind: an accrual that will never be
     * settled, kept on the statement rather than deleted so a realtor s history
     * does not silently rewrite itself between one month and the next.
     */
    public function statusLabel(): string
    {
        return [
            'paid' => 'Paid',
            'owed' => 'Owed',
            'written_off' => 'Written off',
        ][$this->status] ?? 'Owed';
    }

    public function statusKind(): string
    {
        return [
            'paid' => 'pos',
            'owed' => 'medium',
            'written_off' => 'low',
        ][$this->status] ?? 'medium';
    }
}
