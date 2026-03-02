<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReservationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Property $property): bool
    {
        return $user->canAccessProperty($property);
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $user->is_platform_admin
            || $user->canAccessProperty($reservation->property_id);
    }

    public function create(User $user, Property $property): bool
    {
        return $user->hasRoleOnProperty(
            ['owner', 'manager', 'receptionist'],
            $property
        );
    }

    public function update(User $user, Reservation $reservation): bool
    {
        if ($user->is_platform_admin) return true;

        return $user->hasRoleOnProperty(
            ['owner', 'manager', 'receptionist'],
            $reservation->property_id
        );
    }

    public function checkIn(User $user, Reservation $reservation): bool
    {
        if ($user->is_platform_admin) return true;

        return $reservation->status === Reservation::STATUS_CONFIRMED
            && $user->hasRoleOnProperty(
                ['owner', 'manager', 'receptionist'],
                $reservation->property_id
            );
    }

    public function checkOut(User $user, Reservation $reservation): bool
    {
        if ($user->is_platform_admin) return true;

        return $reservation->status === Reservation::STATUS_CHECKED_IN
            && $user->hasRoleOnProperty(
                ['owner', 'manager', 'receptionist'],
                $reservation->property_id
            );
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        if ($user->is_platform_admin) return true;

        return $reservation->isActive()
            && $user->hasRoleOnProperty(
                ['owner', 'manager', 'receptionist'],
                $reservation->property_id
            );
    }

    public function managePayments(User $user, Reservation $reservation): bool
    {
        if ($user->is_platform_admin) return true;

        return $user->hasRoleOnProperty(
            ['owner', 'manager', 'receptionist', 'accountant'],
            $reservation->property_id
        );
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        if ($user->is_platform_admin) return true;

        // Only managers/owners can delete, and only cancelled reservations
        return $reservation->status === Reservation::STATUS_CANCELLED
            && $user->hasRoleOnProperty(['owner', 'manager'], $reservation->property_id);
    }
}
