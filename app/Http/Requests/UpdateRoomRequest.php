<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
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
        $wardId = $this->input('ward_id', $this->route('room')?->ward_id);

        return [
            'ward_id' => ['sometimes', 'required', 'exists:wards,id'],
            'room_number' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('rooms')->where('ward_id', $wardId)->ignore($this->route('room')),
            ],
            'room_type' => ['sometimes', 'required', Rule::in(['normal', 'vip', 'operation'])],
            'capacity' => ['sometimes', 'required', 'integer', 'min:0'],
            'price_per_day' => ['nullable', 'numeric', 'min:0'],
            'is_short_stay' => ['sometimes', 'boolean'],
            'price_12_hours' => ['nullable', 'numeric', 'min:0'],
            'price_24_hours' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
