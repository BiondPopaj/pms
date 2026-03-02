<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_type_id'  => ['required', 'integer', 'exists:room_types,id'],
            'room_number'   => ['required', 'string', 'max:20'],
            'floor'         => ['nullable', 'string', 'max:10'],
            'building'      => ['nullable', 'string', 'max:50'],
            'view_type'     => ['nullable', 'in:sea,garden,pool,city,mountain,courtyard'],
            'is_smoking'    => ['nullable', 'boolean'],
            'is_accessible' => ['nullable', 'boolean'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ];
    }
}
