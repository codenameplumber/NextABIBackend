<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use BelongsToTenant;

    protected $table = 'properties';

    protected $guarded = [];

    protected $casts = [
        'listed_at' => 'datetime', 'sold_at' => 'datetime', 'repriced_at' => 'datetime',
        'price' => 'float', 'prior_price' => 'float', 'promoted' => 'bool',
    ];

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }

    /** The segment key the demand detector groups on. */
    public function segmentKey(): string
    {
        return $this->location . '|' . $this->type . '|' . $this->bedrooms;
    }
}
