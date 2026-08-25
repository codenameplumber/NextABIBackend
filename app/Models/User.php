<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The skeleton's three fillable columns are no longer the whole story: a user
 * belongs to an agency, holds a role, and may be a buyer rather than staff.
 * Leaving the old list in place made those four columns silently drop on every
 * mass assignment outside a seeder — which is exactly the kind of failure that
 * shows up as a 403 nobody can explain.
 */
#[Fillable(['name', 'email', 'password', 'tenant_id', 'role', 'customer_id', 'is_client', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /** Set for buyers; null for agency staff. */
    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function isStaff(): bool { return ! $this->is_client; }

    /** Branded, so the reset mail looks like the product it came from. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordLink($token));
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }

    public function roleLabel(): string
    {
        return [
            'ceo' => 'CEO / Managing Director',
            'sales' => 'Sales Manager',
            'finance' => 'Finance Lead',
            'agent' => 'Sales Agent',
        ][$this->role] ?? 'Team member';
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
