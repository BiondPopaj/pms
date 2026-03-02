<?php

namespace App\Support\Traits;

use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Automatically scopes Eloquent queries to the current tenant (property).
 *
 * Usage: Add `use BelongsToTenant;` to any model that has a property_id column.
 *
 * The current tenant is resolved from:
 * 1. Request attribute 'current_property' (set by IdentifyTenant middleware)
 * 2. Auth user's first active property (fallback)
 */
trait BelongsToTenant
{
    /**
     * Bypass tenant scoping for this query.
     */
    protected static bool $skipTenantScope = false;

    public static function bootBelongsToTenant(): void
    {
        // ── Auto-inject property_id on create ────────────────────────────
        static::creating(function ($model) {
            if (
                !$model->property_id
                && ($property = static::currentTenant())
            ) {
                $model->property_id = $property->id;
            }
        });

        // ── Apply global tenant scope ─────────────────────────────────────
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (static::$skipTenantScope) {
                return;
            }

            // Platform admins can see everything (no scope applied)
            if (Auth::check() && Auth::user()->is_platform_admin) {
                return;
            }

            if ($property = static::currentTenant()) {
                $builder->where(
                    $builder->getModel()->getTable().'.property_id',
                    $property->id
                );
            }
        });
    }

    /**
     * Resolve the current property from the request.
     */
    protected static function currentTenant(): ?Property
    {
        return Request::instance()->get('current_property');
    }

    /**
     * Execute a closure without the tenant scope.
     */
    public static function withoutTenant(callable $callback): mixed
    {
        static::$skipTenantScope = true;

        try {
            return $callback();
        } finally {
            static::$skipTenantScope = false;
        }
    }

    /**
     * Get all records across all tenants (platform admin use).
     */
    public static function allTenants(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }
}
