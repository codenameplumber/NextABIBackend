<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insight extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'facts' => 'array', 'evidence' => 'array',
        'severity' => 'float', 'confidence' => 'float', 'priority' => 'float',
        'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime', 'dismissed_at' => 'datetime',
        'actioned_at' => 'datetime',
    ];

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class)->orderByDesc('is_primary')->orderByDesc('impact');
    }

    public function primaryRecommendation(): ?Recommendation
    {
        return $this->recommendations->firstWhere(fn ($r) => $r->is_primary && ! $r->done_at)
            ?? $this->recommendations->firstWhere(fn ($r) => ! $r->done_at);
    }

    public function isLive(): bool
    {
        return $this->resolved_at === null && $this->dismissed_at === null;
    }

    /** How long this went unfixed. Only meaningful once resolved. */
    public function daysOpen(): ?int
    {
        return $this->resolved_at
            ? (int) $this->first_seen_at->diffInDays($this->resolved_at)
            : null;
    }
}
