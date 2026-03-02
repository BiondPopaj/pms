<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'ulid'                  => $this->ulid,
            'name'                  => $this->name,
            'slug'                  => $this->slug,
            'email'                 => $this->email,
            'phone'                 => $this->phone,
            'website'               => $this->website,
            'logo_url'              => $this->logo_url,
            'property_type'         => $this->property_type,
            'star_rating'           => $this->star_rating,
            'total_rooms'           => $this->total_rooms,
            'city'                  => $this->city,
            'state'                 => $this->state,
            'country'               => $this->country,
            'timezone'              => $this->timezone,
            'currency'              => $this->currency,
            'locale'                => $this->locale,
            'check_in_time'         => $this->check_in_time,
            'check_out_time'        => $this->check_out_time,
            'is_active'             => $this->is_active,
            'is_verified'           => $this->is_verified,
            'verified_at'           => $this->verified_at?->toISOString(),
            'subscription_status'   => $this->subscription_status,
            'subscription_starts_at'=> $this->subscription_starts_at?->toISOString(),
            'subscription_ends_at'  => $this->subscription_ends_at?->toISOString(),
            'trial_ends_at'         => $this->trial_ends_at?->toISOString(),
            'subscription_plan'     => $this->whenLoaded('subscriptionPlan', fn () => [
                'id'   => $this->subscriptionPlan->id,
                'name' => $this->subscriptionPlan->name,
                'slug' => $this->subscriptionPlan->slug,
            ]),
            'users'                 => $this->whenLoaded('users', fn () =>
                $this->users->map(fn ($u) => [
                    'id'        => $u->id,
                    'name'      => $u->name,
                    'email'     => $u->email,
                    'role'      => $u->pivot->role,
                    'is_active' => $u->pivot->is_active,
                ])
            ),
            'reservations_count'    => $this->whenCounted('reservations'),
            'rooms_count'           => $this->whenCounted('rooms'),
            'users_count'           => $this->whenCounted('users'),
            'guests_count'          => $this->whenCounted('guests'),
            'created_at'            => $this->created_at->toISOString(),
            'updated_at'            => $this->updated_at->toISOString(),
        ];
    }
}
