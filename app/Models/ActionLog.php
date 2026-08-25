<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActionLog extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['impact' => 'float', 'affected' => 'int'];

    public function insight(): BelongsTo { return $this->belongsTo(Insight::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function hasDiff(): bool { return $this->before !== null && $this->after !== null; }
}
