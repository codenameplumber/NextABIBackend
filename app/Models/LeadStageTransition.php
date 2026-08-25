<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadStageTransition extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['at' => 'datetime'];

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
}
