<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recommendation extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array', 'impact' => 'float',
        'is_primary' => 'bool', 'done_at' => 'datetime',
    ];

    public function insight(): BelongsTo { return $this->belongsTo(Insight::class); }

    public function isDone(): bool { return $this->done_at !== null; }

    public function effortLabel(): string
    {
        return ['low' => 'Quick', 'medium' => 'Moderate', 'high' => 'Involved'][$this->effort] ?? $this->effort;
    }
}
