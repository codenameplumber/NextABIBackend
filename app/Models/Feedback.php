<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Collected at journey milestones, not on a screen anyone browses.
 *
 * Feedback lost its menu item in the pivot, but not its purpose: two of the
 * fourteen detectors run on satisfaction. Asking once the journey is under way
 * and again when it completes is what keeps them fed — and it asks at the two
 * moments a buyer actually has an opinion worth recording.
 */
class Feedback extends Model
{
    use BelongsToTenant;

    protected $table = 'feedbacks';

    protected $guarded = [];

    protected $casts = ['tags' => 'array', 'sentiment' => 'float', 'rating' => 'int'];

    public const TRIGGERS = [
        'journey_started' => 'After the first instalment',
        'completed' => 'On completion',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    public function investor(): BelongsTo { return $this->belongsTo(Customer::class, 'customer_id'); }

    public function commitment(): BelongsTo { return $this->belongsTo(Deal::class, 'deal_id'); }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }

    public function triggerLabel(): ?string
    {
        return self::TRIGGERS[$this->trigger] ?? null;
    }
}
