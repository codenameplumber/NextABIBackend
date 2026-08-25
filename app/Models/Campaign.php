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
    ];

    public function leads(): HasMany { return $this->hasMany(Lead::class); }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }
}
