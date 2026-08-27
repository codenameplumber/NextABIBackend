<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Nigeria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An investor.
 *
 * Still `customers` in the schema — the metrics layer and three detectors read
 * that table by name. The product calls them investors.
 *
 * `agent_id` is the realtor who owns them, and a null there is not missing
 * data: it IS the unassigned queue, the list an admin works through every
 * morning. Someone who arrived without a referral code is a real investor with
 * no realtor attached yet, and the approval gate on their first commitment is
 * what stops that becoming an ownerless commission.
 */
class Customer extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'since' => 'datetime', 'escalated_at' => 'datetime',
        'recovery_opened_at' => 'datetime', 'ltv' => 'float',
        'satisfaction' => 'float', 'escalated' => 'bool',
        'assigned_at' => 'datetime',
    ];

    /* ------------------------------------------------------- relationships */

    /** The realtor who owns this investor. Null means unassigned. */
    public function realtor(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }

    /** Kept under the old name: the engine and several views still use it. */
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }

    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }

    public function user(): HasOne { return $this->hasOne(User::class); }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }

    /** The same rows, in the product's language. */
    public function commitments(): HasMany { return $this->hasMany(Deal::class); }

    public function payments(): HasMany { return $this->hasMany(Payment::class); }

    public function feedback(): HasMany { return $this->hasMany(Feedback::class); }

    public function tickets(): HasMany { return $this->hasMany(SupportTicket::class); }

    /* -------------------------------------------------------------- scopes */

    /** The queue an admin opens every morning. */
    public function scopeUnassigned(Builder $q): Builder
    {
        return $q->whereNull('agent_id');
    }

    public function scopeAssigned(Builder $q): Builder
    {
        return $q->whereNotNull('agent_id');
    }

    public function isAssigned(): bool { return $this->agent_id !== null; }

    /* ------------------------------------------------------------- profile */

    public function incomeLabel(): ?string { return Nigeria::incomeLabel($this->income_bracket); }

    public function occupationLabel(): ?string { return Nigeria::occupationLabel($this->occupation); }

    public function sourceLabel(): ?string { return Nigeria::sourceLabel($this->heard_about_us); }

    /**
     * "Ikeja, Lagos" — the way anyone here would actually say it.
     *
     * NOT named where(): Eloquent forwards unknown static calls to the query
     * builder, and an instance method by that name shadows Model::where()
     * for the whole class.
     */
    public function placeLabel(): ?string
    {
        return $this->lga && $this->state ? $this->lga.', '.$this->state : $this->state;
    }

    /* -------------------------------------------------------------- naming */

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->take(2)
            ->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    }

    public function firstName(): string { return explode(' ', $this->name)[0]; }
}
