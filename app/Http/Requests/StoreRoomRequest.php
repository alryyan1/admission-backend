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
            'room_type' => ['required', Rule::in(['normal', 'vip', 'operation', 'ward'])],
            'capacity' => ['required', 'integer', 'min:0'],
            'price_per_day' => ['nullable', 'numeric', 'min:0'],
            'is_short_stay' => ['sometimes', 'boolean'],
            'price_12_hours' => ['nullable', 'numeric', 'min:0'],
            'price_24_hours' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
