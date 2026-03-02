<?php

namespace App\Http\Resources\Property;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'room_number'         => $this->room_number,
            'floor'               => $this->floor,
            'building'            => $this->building,
            'view_type'           => $this->view_type,
            'housekeeping_status' => $this->housekeeping_status,
            'occupancy_status'    => $this->occupancy_status,
            'is_smoking'          => $this->is_smoking,
            'is_accessible'       => $this->is_accessible,
            'is_active'           => $this->is_active,
            'notes'               => $this->notes,
            'last_cleaned_at'     => $this->last_cleaned_at?->toISOString(),
            'created_at'          => $this->created_at->toISOString(),

            'room_type' => $this->whenLoaded('roomType', fn () => [
                'id'           => $this->roomType->id,
                'name'         => $this->roomType->name,
                'code'         => $this->roomType->code,
                'bed_type'     => $this->roomType->bed_type,
                'max_occupancy'=> $this->roomType->max_occupancy,
                'amenities'    => $this->roomType->amenities,
            ]),
            'assigned_housekeeper' => $this->whenLoaded('assignedHousekeeper', fn () =>
                $this->assignedHousekeeper ? [
                    'id'   => $this->assignedHousekeeper->id,
                    'name' => $this->assignedHousekeeper->name,
                ] : null
            ),
            'current_reservation' => $this->whenLoaded('currentReservation', fn () =>
                $this->currentReservation ? [
                    'id'                 => $this->currentReservation->id,
                    'reservation_number' => $this->currentReservation->reservation_number,
                    'check_out_date'     => $this->currentReservation->check_out_date?->toDateString(),
                    'guest_name'         => $this->currentReservation->guest->first_name
                        .' '.$this->currentReservation->guest->last_name,
                ] : null
            ),
        ];
    }
}
