<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'joined_at' => 'datetime', 'active' => 'bool',
        'coaching' => 'bool', 'skill' => 'float',
    ];

    public function leads(): HasMany { return $this->hasMany(Lead::class, 'assigned_agent_id'); }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->take(2)
            ->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    }

    public function firstName(): string { return explode(' ', $this->name)[0]; }
}
