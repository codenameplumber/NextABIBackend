<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;

/**
 * Every domain row belongs to exactly one agency.
 *
 * The global scope is deliberately not optional. Forgetting a tenant filter in
 * a BI product does not produce an error — it produces a plausible-looking
 * number containing someone else's business. The only way out is
 * withoutTenantScope(), which is loud enough to notice in review.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (! $model->tenant_id && $id = static::currentTenantId()) {
                $model->tenant_id = $id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Resolved from the container so jobs and the console can set it explicitly. */
    public static function currentTenantId(): ?int
    {
        return app()->bound('nexabi.tenant')
            ? app('nexabi.tenant')?->id
            : auth()->user()?->tenant_id;
    }

    public function scopeWithoutTenantScope(Builder $q): Builder
    {
        return $q->withoutGlobalScope(TenantScope::class);
    }

    public function scopeForTenant(Builder $q, int|Tenant $tenant): Builder
    {
        return $q->withoutGlobalScope(TenantScope::class)
            ->where($q->getModel()->getTable().'.tenant_id', $tenant instanceof Tenant ? $tenant->id : $tenant);
    }
}

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = app()->bound('nexabi.tenant')
            ? app('nexabi.tenant')?->id
            : auth()->user()?->tenant_id;

        if ($id) {
            $builder->where($model->getTable().'.tenant_id', $id);
        }
    }
}
