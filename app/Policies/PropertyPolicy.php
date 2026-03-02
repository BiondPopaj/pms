<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PropertyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // filtered by BelongsToTenant / user relationships
    }

    public function view(User $user, Property $property): bool
    {
        return $user->is_platform_admin || $user->canAccessProperty($property);
    }

    public function create(User $user): bool
    {
        return $user->is_platform_admin;
    }

    public function update(User $user, Property $property): bool
    {
        if ($user->is_platform_admin) return true;
        return $user->hasRoleOnProperty(['owner', 'manager'], $property);
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->is_platform_admin;
    }

    public function manageUsers(User $user, Property $property): bool
    {
        if ($user->is_platform_admin) return true;
        return $user->hasRoleOnProperty(['owner', 'manager'], $property);
    }

    public function manageSettings(User $user, Property $property): bool
    {
        if ($user->is_platform_admin) return true;
        return $user->hasRoleOnProperty(['owner'], $property);
    }

    public function viewFinancials(User $user, Property $property): bool
    {
        if ($user->is_platform_admin) return true;
        return $user->hasRoleOnProperty(['owner', 'manager', 'accountant'], $property);
    }

    public function runNightAudit(User $user, Property $property): bool
    {
        if ($user->is_platform_admin) return true;
        return $user->hasRoleOnProperty(['owner', 'manager', 'accountant'], $property);
    }
}
