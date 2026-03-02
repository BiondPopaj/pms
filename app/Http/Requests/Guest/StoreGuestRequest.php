<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'nationality'   => ['nullable', 'string', 'size:2'],
            'language'      => ['nullable', 'string', 'max:5'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender'        => ['nullable', 'in:male,female,other'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'size:2'],
            'company_name'  => ['nullable', 'string', 'max:255'],
            'vat_number'    => ['nullable', 'string', 'max:50'],
            'passport_number'=> ['nullable', 'string', 'max:50'],
            'id_type'       => ['nullable', 'in:passport,driver_license,national_id'],
            'id_number'     => ['nullable', 'string', 'max:50'],
            'id_expiry'     => ['nullable', 'date'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'internal_notes'=> ['nullable', 'string', 'max:2000'],
            'preferences'   => ['nullable', 'array'],
            'vip_status'    => ['nullable', 'in:silver,gold,platinum'],
        ];
    }
}
