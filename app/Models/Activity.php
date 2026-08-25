<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['at' => 'datetime'];

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
}
