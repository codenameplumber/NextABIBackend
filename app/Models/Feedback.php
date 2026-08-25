<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feedback extends Model
{
    use BelongsToTenant;

    protected $table = 'feedbacks';

    protected $guarded = [];

    protected $casts = ['tags' => 'array', 'sentiment' => 'float', 'rating' => 'int'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
}
