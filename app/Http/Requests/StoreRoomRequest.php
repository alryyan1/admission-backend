<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ward_id' => ['required', 'exists:wards,id'],
            'room_number' => [
                'required', 'string', 'max:50',
                Rule::unique('rooms')->where('ward_id', $this->input('ward_id')),
            ],
            'room_type' => ['required', Rule::exists('room_types', 'code')],
            'capacity' => ['required', 'integer', 'min:0'],
            'auto_create_beds' => ['sometimes', 'boolean'],
            'price_per_day' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
