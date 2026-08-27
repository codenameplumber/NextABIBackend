<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
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
        'commission_rate' => 'float', 'listed' => 'bool', 'images' => 'array',
        'min_duration_months' => 'int', 'max_duration_months' => 'int',
    ];

    /**
     * The ceiling on what a realtor can earn from one sale.
     *
     * Set here rather than only in a form request, because the cap is a
     * business rule and not a validation preference — every path that writes a
     * rate has to obey it, including a seeder, a console command and an import.
     */
    public const MAX_COMMISSION_RATE = 0.15;

    /** Durations an investor may pick, in months. */
    public const DURATION_FLOOR = 1;
    public const DURATION_CEILING = 60;

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }

    public function materials(): HasMany { return $this->hasMany(PromotionalMaterial::class); }

    /* -------------------------------------------------------------- scopes */

    /** What a buyer is allowed to see and commit to. */
    public function scopeOnMarket(Builder $q): Builder
    {
        return $q->where('status', 'available')->where('listed', true);
    }

    public function isOnMarket(): bool
    {
        return $this->status === 'available' && $this->listed;
    }

    /* ---------------------------------------------------------- commission */

    /**
     * Clamp a rate into the permitted band.
     *
     * Accepts either 12 or 0.12 — a percentage typed into a form and a rate
     * held in the database are the same intention written two ways, and
     * silently storing 12.0 as a rate would pay a realtor twelve times the
     * property value.
     */
    public static function normaliseRate(float|int|string|null $input): float
    {
        $value = (float) $input;

        if ($value > 1) {
            $value = $value / 100;
        }

        return max(0.0, min(self::MAX_COMMISSION_RATE, round($value, 3)));
    }

    public function commissionOn(?float $value = null): float
    {
        return ($value ?? $this->price) * (float) $this->commission_rate;
    }

    public function commissionPct(): float
    {
        return (float) $this->commission_rate * 100;
    }

    /* ------------------------------------------------------------ duration */

    /** @return int[] */
    public function durationOptions(): array
    {
        $min = max(self::DURATION_FLOOR, (int) $this->min_duration_months);
        $max = min(self::DURATION_CEILING, (int) $this->max_duration_months);

        if ($max < $min) {
            $max = $min;
        }

        // The terms people actually ask for, filtered to what this property
        // allows — a free-text month count invites 7-month plans nobody wants
        // to reconcile.
        $offered = array_values(array_filter(
            [3, 6, 9, 12, 18, 24, 36, 48, 60],
            fn ($m) => $m >= $min && $m <= $max,
        ));

        return $offered ?: [$min];
    }

    public function allowsDuration(?int $months): bool
    {
        return $months !== null && in_array($months, $this->durationOptions(), true);
    }

    public function instalmentFor(int $months): float
    {
        return $months > 0 ? $this->price / $months : $this->price;
    }

    /** The segment key the demand detector groups on. */
    public function segmentKey(): string
    {
        return $this->location.'|'.$this->type.'|'.$this->bedrooms;
    }
}
