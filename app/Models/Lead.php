<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
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

    public function isStale(int $days = 14): bool
    {
        return $this->is_open && $this->daysSinceContact() >= $days;
    }
}
