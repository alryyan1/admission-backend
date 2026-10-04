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
            'room_type' => ['sometimes', 'required', Rule::exists('room_types', 'code')],
            'capacity' => ['sometimes', 'required', 'integer', 'min:0'],
            'price_per_day' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
