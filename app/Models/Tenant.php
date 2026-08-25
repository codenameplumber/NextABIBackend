<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $guarded = [];

    protected $casts = ['settings' => 'array', 'detectors_ran_at' => 'datetime'];

    public function users(): HasMany { return $this->hasMany(User::class); }

    public function agents(): HasMany { return $this->hasMany(Agent::class); }

    public function insights(): HasMany { return $this->hasMany(Insight::class); }

    /** Policies are set by accepting recommendations, not configured by hand. */
    public function policy(string $key, mixed $default = null): mixed
    {
        return $this->policies()[$key] ?? $default;
    }

    /**
     * Stored flat, deliberately.
     *
     * Policy keys are dotted — `leads.followUpDays`, `pricing.autoAdjust` —
     * and data_set() reads a dot as nesting. That turned one policy into a
     * nested array, and the settings screen then tried to print an array as a
     * string. The dot is part of the name, not a path.
     */
    public function setPolicy(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        $policies = $settings['policies'] ?? [];
        $policies[$key] = $value;
        $settings['policies'] = $policies;

        $this->update(['settings' => $settings]);
    }

    /** @return array<string, mixed> flat map of policy name => value */
    public function policies(): array
    {
        return collect($this->settings['policies'] ?? [])
            // Anything written before the flattening fix arrives nested; walk
            // it back to dotted keys rather than crashing the screen.
            ->flatMap(function ($value, $key) {
                if (! is_array($value)) {
                    return [$key => $value];
                }

                return collect($value)->mapWithKeys(
                    fn ($v, $k) => [$key.'.'.$k => is_array($v) ? json_encode($v) : $v],
                );
            })
            ->all();
    }
}
