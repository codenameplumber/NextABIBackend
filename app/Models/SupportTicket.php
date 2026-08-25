<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['replied_at' => 'datetime'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
}
