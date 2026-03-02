<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_type_id'        => ['sometimes', 'integer', 'exists:room_types,id'],
            'room_number'         => ['sometimes', 'string', 'max:20'],
            'floor'               => ['nullable', 'string', 'max:10'],
            'building'            => ['nullable', 'string', 'max:50'],
            'view_type'           => ['nullable', 'in:sea,garden,pool,city,mountain,courtyard'],
            'housekeeping_status' => ['sometimes', 'in:clean,dirty,inspecting,out_of_order'],
            'is_smoking'          => ['sometimes', 'boolean'],
            'is_accessible'       => ['sometimes', 'boolean'],
            'is_active'           => ['sometimes', 'boolean'],
            'notes'               => ['nullable', 'string', 'max:500'],
        ];
    }
}
