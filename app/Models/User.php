<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Three kinds of people sign in here.
 *
 *   admin     runs the platform — the console at /app
 *   realtor   sources investors — /realtor, and only once approved
 *   investor  buys — /invest
 *
 * `type` decides which building you are in. `role` is a different question that
 * only means anything for admins: which lens the insight feed is ranked
 * through (ceo / sales / finance). Those two were one column before the pivot,
 * which worked only while everyone who signed in was staff.
 */
#[Fillable(['name', 'email', 'password', 'tenant_id', 'role', 'type',
    'customer_id', 'agent_id', 'is_client', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const TYPES = ['admin', 'realtor', 'investor'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    /** Set for investors; null for everyone else. */
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    /** The investor record, in the product's language. */
    public function investor(): BelongsTo { return $this->belongsTo(Customer::class, 'customer_id'); }

    /** Set for realtors; null for everyone else. */
    public function realtor(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }

    /* ---------------------------------------------------------------- type */

    public function isAdmin(): bool { return $this->type === 'admin'; }

    public function isRealtor(): bool { return $this->type === 'realtor'; }

    public function isInvestor(): bool { return $this->type === 'investor'; }

    /**
     * A realtor who has actually been let in.
     *
     * Signing up makes you a realtor row; an admin approving you makes you one
     * who can do anything. Everything in the realtor area asks this, not
     * isRealtor().
     */
    public function isApprovedRealtor(): bool
    {
        return $this->isRealtor() && (bool) $this->realtor?->isApproved();
    }

    /** Kept for the paths that still ask the old question. */
    public function isStaff(): bool { return $this->isAdmin(); }

    /**
     * Where this account belongs after signing in.
     *
     * Falls back when an area has not been built yet, so a half-finished
     * surface redirects somewhere real instead of throwing on a missing route.
     * Remove the fallbacks once every area exists.
     */
    public function homeRoute(): string
    {
        $candidates = match ($this->type) {
            'investor' => ['invest.overview', 'portal.overview'],
            'realtor' => ['realtor.dashboard', 'realtor.pending'],
            default => ['app.dashboard'],
        };

        foreach ($candidates as $route) {
            if (\Illuminate\Support\Facades\Route::has($route)) {
                return $route;
            }
        }

        return 'app.dashboard';
    }

    /* -------------------------------------------------------------- naming */

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }

    public function roleLabel(): string
    {
        if ($this->isRealtor()) {
            return 'Realtor';
        }

        if ($this->isInvestor()) {
            return 'Investor';
        }

        return [
            'ceo' => 'CEO / Managing Director',
            'sales' => 'Sales Manager',
            'finance' => 'Finance Lead',
            'agent' => 'Sales Agent',
        ][$this->role] ?? 'Team member';
    }

    /** Branded, so the reset mail looks like the product it came from. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordLink($token));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_client' => 'bool',
        ];
    }
}
