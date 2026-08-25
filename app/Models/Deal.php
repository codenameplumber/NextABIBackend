<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime', 'closed_at' => 'datetime',
        'value' => 'float', 'commission' => 'float', 'won' => 'bool',
    ];

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}
