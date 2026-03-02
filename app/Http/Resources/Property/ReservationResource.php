<?php

namespace App\Http\Resources\Property;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'reservation_number'      => $this->reservation_number,
            'status'                  => $this->status,
            'status_label'            => \App\Models\Reservation::statuses()[$this->status] ?? null,
            'payment_status'          => $this->payment_status,
            'check_in_date'           => $this->check_in_date?->toDateString(),
            'check_out_date'          => $this->check_out_date?->toDateString(),
            'nights'                  => $this->nights,
            'adults'                  => $this->adults,
            'children'                => $this->children,
            'infants'                 => $this->infants,
            'room_rate'               => $this->room_rate,
            'total_room'              => $this->total_room,
            'total_extras'            => $this->total_extras,
            'total_tax'               => $this->total_tax,
            'total_discount'          => $this->total_discount,
            'total_amount'            => $this->total_amount,
            'total_paid'              => $this->total_paid,
            'balance_due'             => $this->balance_due,
            'currency'                => $this->currency,
            'ota_confirmation_number' => $this->ota_confirmation_number,
            'ota_commission'          => $this->ota_commission,
            'special_requests'        => $this->special_requests,
            'internal_notes'          => $this->internal_notes,
            'is_group_booking'        => $this->is_group_booking,
            'extras'                  => $this->extras,
            'confirmed_at'            => $this->confirmed_at?->toISOString(),
            'checked_in_at'           => $this->checked_in_at?->toISOString(),
            'checked_out_at'          => $this->checked_out_at?->toISOString(),
            'cancelled_at'            => $this->cancelled_at?->toISOString(),
            'cancellation_reason'     => $this->cancellation_reason,
            'cancellation_fee'        => $this->cancellation_fee,
            'created_at'              => $this->created_at->toISOString(),
            'updated_at'              => $this->updated_at->toISOString(),

            // Relationships
            'guest'         => $this->whenLoaded('guest', fn () => [
                'id'           => $this->guest->id,
                'first_name'   => $this->guest->first_name,
                'last_name'    => $this->guest->last_name,
                'full_name'    => $this->guest->first_name.' '.$this->guest->last_name,
                'email'        => $this->guest->email,
                'phone'        => $this->guest->phone,
                'nationality'  => $this->guest->nationality,
                'vip_status'   => $this->guest->vip_status,
            ]),
            'room_type'     => $this->whenLoaded('roomType', fn () => [
                'id'   => $this->roomType->id,
                'name' => $this->roomType->name,
                'code' => $this->roomType->code,
            ]),
            'room'          => $this->whenLoaded('room', fn () => $this->room ? [
                'id'          => $this->room->id,
                'room_number' => $this->room->room_number,
                'floor'       => $this->room->floor,
            ] : null),
            'rate_plan'     => $this->whenLoaded('ratePlan', fn () => $this->ratePlan ? [
                'id'       => $this->ratePlan->id,
                'name'     => $this->ratePlan->name,
                'code'     => $this->ratePlan->code,
                'meal_plan'=> $this->ratePlan->meal_plan,
            ] : null),
            'booking_source'=> $this->whenLoaded('bookingSource', fn () => $this->bookingSource ? [
                'id'    => $this->bookingSource->id,
                'name'  => $this->bookingSource->name,
                'code'  => $this->bookingSource->code,
                'color' => $this->bookingSource->color,
            ] : null),
            'created_by'    => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),
        ];
    }
}
