<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_type_id'           => ['sometimes', 'integer', 'exists:room_types,id'],
            'room_id'                => ['nullable', 'integer', 'exists:rooms,id'],
            'rate_plan_id'           => ['nullable', 'integer', 'exists:rate_plans,id'],
            'booking_source_id'      => ['nullable', 'integer', 'exists:booking_sources,id'],
            'check_in_date'          => ['sometimes', 'date'],
            'check_out_date'         => ['sometimes', 'date', 'after:check_in_date'],
            'adults'                 => ['sometimes', 'integer', 'min:1', 'max:20'],
            'children'               => ['nullable', 'integer', 'min:0', 'max:20'],
            'infants'                => ['nullable', 'integer', 'min:0', 'max:10'],
            'room_rate'              => ['sometimes', 'numeric', 'min:0'],
            'ota_confirmation_number'=> ['nullable', 'string', 'max:100'],
            'ota_commission'         => ['nullable', 'numeric', 'min:0'],
            'special_requests'       => ['nullable', 'string', 'max:2000'],
            'internal_notes'         => ['nullable', 'string', 'max:2000'],
            'extras'                 => ['nullable', 'array'],
        ];
    }
}
