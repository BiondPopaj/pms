<?php

namespace App\Http\Resources\Property;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'first_name'     => $this->first_name,
            'last_name'      => $this->last_name,
            'full_name'      => $this->first_name.' '.$this->last_name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'nationality'    => $this->nationality,
            'language'       => $this->language,
            'date_of_birth'  => $this->date_of_birth?->toDateString(),
            'gender'         => $this->gender,
            'address_line1'  => $this->address_line1,
            'address_line2'  => $this->address_line2,
            'city'           => $this->city,
            'state'          => $this->state,
            'postal_code'    => $this->postal_code,
            'country'        => $this->country,
            'company_name'   => $this->company_name,
            'vat_number'     => $this->vat_number,
            'id_type'        => $this->id_type,
            'id_expiry'      => $this->id_expiry?->toDateString(),
            'total_revenue'  => $this->total_revenue,
            'total_stays'    => $this->total_stays,
            'vip_status'     => $this->vip_status,
            'notes'          => $this->notes,
            'is_blacklisted' => $this->is_blacklisted,
            'preferences'    => $this->preferences,
            'created_at'     => $this->created_at->toISOString(),
            'updated_at'     => $this->updated_at->toISOString(),

            // Sensitive fields only shown when explicitly loaded
            'passport_number'=> $this->when(
                $request->user()?->hasMinimumRole('manager', $request->get('current_property')),
                $this->passport_number
            ),
            'id_number'      => $this->when(
                $request->user()?->hasMinimumRole('manager', $request->get('current_property')),
                $this->id_number
            ),
            'internal_notes' => $this->when(
                $request->user()?->hasMinimumRole('receptionist', $request->get('current_property')),
                $this->internal_notes
            ),
        ];
    }
}
