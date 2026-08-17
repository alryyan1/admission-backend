<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBedRequest extends FormRequest
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
            'room_id' => ['required', 'exists:rooms,id'],
            'bed_number' => [
                'required', 'string', 'max:50',
                Rule::unique('beds')->where('room_id', $this->input('room_id')),
            ],
            'unit_type' => ['sometimes', Rule::in(['bed', 'chair'])],
            'status' => ['sometimes', Rule::in(['available', 'occupied', 'maintenance'])],
        ];
    }
}
