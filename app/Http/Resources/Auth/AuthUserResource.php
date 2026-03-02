<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'ulid'               => $this->ulid,
            'name'               => $this->name,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'avatar_url'         => $this->avatar_url,
            'locale'             => $this->locale,
            'timezone'           => $this->timezone,
            'is_platform_admin'  => $this->is_platform_admin,
            'is_active'          => $this->is_active,
            'two_factor_enabled' => $this->two_factor_enabled,
            'email_verified_at'  => $this->email_verified_at?->toISOString(),
            'last_login_at'      => $this->last_login_at?->toISOString(),
            'last_login_ip'      => $this->last_login_ip,
            'preferences'        => $this->preferences,
            'created_at'         => $this->created_at->toISOString(),
            'properties'         => $this->whenLoaded('activeProperties', function () {
                return $this->activeProperties->map(fn ($p) => [
                    'id'            => $p->id,
                    'ulid'          => $p->ulid,
                    'name'          => $p->name,
                    'logo_url'      => $p->logo_url,
                    'property_type' => $p->property_type,
                    'currency'      => $p->currency,
                    'timezone'      => $p->timezone,
                    'role'          => $p->pivot->role,
                ]);
            }),
        ];
    }
}
