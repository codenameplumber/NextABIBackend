<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'since' => 'datetime', 'escalated_at' => 'datetime',
        'recovery_opened_at' => 'datetime', 'ltv' => 'float',
        'satisfaction' => 'float', 'escalated' => 'bool',
    ];

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }

    public function payments(): HasMany { return $this->hasMany(Payment::class); }

    public function feedback(): HasMany { return $this->hasMany(Feedback::class); }

    public function tickets(): HasMany { return $this->hasMany(SupportTicket::class); }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->take(2)
            ->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    }

    public function firstName(): string { return explode(' ', $this->name)[0]; }
}
