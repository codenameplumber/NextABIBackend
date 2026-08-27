<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'start_at' => 'datetime', 'end_at' => 'datetime', 'paused_at' => 'datetime',
        'spend' => 'float', 'impressions' => 'int', 'clicks' => 'int',
        'expected_leads' => 'int', 'expected_revenue' => 'float',
        'actual_leads' => 'int', 'actual_revenue' => 'float',
        'manual' => 'bool',
    ];

    /**
     * What a marketing record can be.
     *
     * The first four carry tracking of their own; the rest never will, and
     * they are exactly the ones an agency here spends real money on.
     */
    public const CHANNELS = [
        'Social' => 'Social media',
        'Paid Search' => 'Paid search',
        'Marketplace' => 'Property portal',
        'Offline' => 'Billboard or print',
        'Radio' => 'Radio',
        'Television' => 'Television',
        'Community' => 'Church, mosque or association',
        'Event' => 'Event or exhibition',
        'Direct' => 'Direct and word of mouth',
    ];

    public function leads(): HasMany { return $this->hasMany(Lead::class); }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }

    public function isPaused(): bool { return $this->status === 'paused'; }

    /* ------------------------------------------------- expected vs actual */

    /** Whether anyone has told us how this one went. */
    public function isCounted(): bool
    {
        return $this->actual_leads !== null || $this->actual_revenue !== null;
    }

    /**
     * Leads to judge this record by.
     *
     * A hand-entered record has no campaign_id on anything, so the attributed
     * count is always zero and always wrong. Prefer what was recorded; fall
     * back to attribution for the tracked rows.
     */
    public function leadsAchieved(): int
    {
        // withCount('leads') if the caller loaded it, a query only if not.
        // The marketing table draws sixty of these; without the first branch
        // that is sixty extra round trips to print one column.
        return $this->actual_leads
            ?? (int) ($this->leads_count ?? $this->leads()->count());
    }

    public function revenueAchieved(): float
    {
        return $this->actual_revenue
            ?? (float) ($this->won_value ?? $this->deals()->where('won', true)->sum('value'));
    }

    /**
     * Delivery against what was promised, as a ratio. Null when there is
     * nothing to compare — an expectation nobody recorded is not a failure.
     */
    public function deliveryRate(): ?float
    {
        if (! $this->expected_leads) {
            return null;
        }

        return $this->leadsAchieved() / $this->expected_leads;
    }

    /** Naira of spend per lead actually produced. Null while uncounted. */
    public function costPerLead(): ?float
    {
        $leads = $this->leadsAchieved();

        return $leads > 0 ? (float) $this->spend / $leads : null;
    }
}
