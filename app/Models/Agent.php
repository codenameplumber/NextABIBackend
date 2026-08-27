<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A realtor.
 *
 * The table is still called `agents` because the metrics layer, four detectors,
 * the playbooks and the action bus all speak that name — renaming it buys a
 * nicer word and risks the entire engine. The product says "realtor" everywhere
 * a person can read it.
 *
 * Three things make this more than the old agent record: a login, a referral
 * code, and an approval state. Nothing works until an admin approves them.
 */
class Agent extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'joined_at' => 'datetime', 'approved_at' => 'datetime',
        'active' => 'bool', 'coaching' => 'bool', 'skill' => 'float',
    ];

    public const STATUSES = [
        'pending' => 'Awaiting approval',
        'approved' => 'Approved',
        'suspended' => 'Suspended',
    ];

    /* ------------------------------------------------------- relationships */

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function leads(): HasMany { return $this->hasMany(Lead::class, 'assigned_agent_id'); }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }

    /** Investors this realtor owns. Null owner is the unassigned queue. */
    public function investors(): HasMany { return $this->hasMany(Customer::class); }

    public function payouts(): HasMany { return $this->hasMany(CommissionPayout::class); }

    /* -------------------------------------------------------------- scopes */

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    /* --------------------------------------------------------------- state */

    public function isApproved(): bool { return $this->status === 'approved'; }

    public function isPending(): bool { return $this->status === 'pending'; }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? 'Unknown';
    }

    /* ---------------------------------------------------------------- code */

    /**
     * Generate a referral code that a human can read down a phone line.
     *
     * Excludes the characters that get misheard or mistyped — 0/O, 1/I/L, 5/S,
     * 8/B — because this code is dictated aloud, written on flyers and typed
     * from memory. A collision-proof code nobody can transcribe is worse than a
     * slightly shorter one that survives the journey.
     */
    public static function generateCode(string $name): string
    {
        $alphabet = 'ACDEFGHJKMNPQRTUVWXY234679';

        $initials = collect(explode(' ', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))
            ->implode('');

        $initials = str_pad($initials ?: 'NX', 2, 'X');

        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = $initials.$suffix;
        } while (static::withoutTenantScope()->where('code', $code)->exists());

        return $code;
    }

    /** The link a realtor copies and sends. Arrives with the code prefilled. */
    public function registrationLink(): string
    {
        return route('register', ['ref' => $this->code]);
    }

    public function questionnaireLink(): string
    {
        return route('enquire', ['ref' => $this->code]);
    }

    /* -------------------------------------------------------------- naming */

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->take(2)
            ->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    }

    public function firstName(): string { return explode(' ', $this->name)[0]; }
}
