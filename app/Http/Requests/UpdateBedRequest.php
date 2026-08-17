<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBedRequest extends FormRequest
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
        $roomId = $this->input('room_id', $this->route('bed')?->room_id);

        return [
            'room_id' => ['sometimes', 'required', 'exists:rooms,id'],
            'bed_number' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('beds')->where('room_id', $roomId)->ignore($this->route('bed')),
            ],
            'unit_type' => ['sometimes', Rule::in(['bed', 'chair'])],
            'status' => ['sometimes', Rule::in(['available', 'occupied', 'maintenance'])],
        ];
    }
}
