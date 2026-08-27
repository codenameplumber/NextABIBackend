<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Nigeria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'last_contact_at' => 'datetime', 'converted_at' => 'datetime',
        'follow_up_at' => 'datetime', 'reassigned_at' => 'datetime',
        'rescored_at' => 'datetime', 'budget' => 'float',
        'is_open' => 'bool', 'imported' => 'bool', 'score' => 'int',
    ];

    /** Funnel order. 'lost' is not a stage, it is an exit from one. */
    public const STAGES = ['new', 'contacted', 'qualified', 'viewing', 'negotiating', 'won'];

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class, 'assigned_agent_id'); }

    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }

    public function transitions(): HasMany { return $this->hasMany(LeadStageTransition::class); }

    public function daysSinceContact(): int
    {
        return $this->last_contact_at ? (int) $this->last_contact_at->diffInDays(now()) : 0;
    }

    /**
     * The realtor who captured this lead.
     *
     * A lead arrives through a realtor's questionnaire link or an advert. If it
     * later becomes an investor, this is who gets the credit.
     */
    public function realtor(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

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
        return $this->lga && $this->state ? $this->lga.", ".$this->state : $this->state;
    }

    public function isStale(int $days = 14): bool
    {
        return $this->is_open && $this->daysSinceContact() >= $days;
    }
}
