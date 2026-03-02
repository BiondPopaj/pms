<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_id'               => ['required', 'integer', 'exists:guests,id'],
            'room_type_id'           => ['required', 'integer', 'exists:room_types,id'],
            'room_id'                => ['nullable', 'integer', 'exists:rooms,id'],
            'rate_plan_id'           => ['nullable', 'integer', 'exists:rate_plans,id'],
            'booking_source_id'      => ['nullable', 'integer', 'exists:booking_sources,id'],
            'check_in_date'          => ['required', 'date', 'after_or_equal:today'],
            'check_out_date'         => ['required', 'date', 'after:check_in_date'],
            'adults'                 => ['required', 'integer', 'min:1', 'max:20'],
            'children'               => ['nullable', 'integer', 'min:0', 'max:20'],
            'infants'                => ['nullable', 'integer', 'min:0', 'max:10'],
            'room_rate'              => ['required', 'numeric', 'min:0'],
            'ota_confirmation_number'=> ['nullable', 'string', 'max:100'],
            'ota_commission'         => ['nullable', 'numeric', 'min:0'],
            'special_requests'       => ['nullable', 'string', 'max:2000'],
            'internal_notes'         => ['nullable', 'string', 'max:2000'],
            'is_group_booking'       => ['nullable', 'boolean'],
            'extras'                 => ['nullable', 'array'],
            'extras.*.description'   => ['required_with:extras', 'string'],
            'extras.*.amount'        => ['required_with:extras', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'check_in_date.after_or_equal' => 'Check-in date cannot be in the past.',
            'check_out_date.after'          => 'Check-out date must be after check-in date.',
        ];
    }
}
