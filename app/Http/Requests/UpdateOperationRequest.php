<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperationRequest extends FormRequest
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
            'surgeon_id' => ['sometimes', 'integer'],
            'operation_room_id' => ['sometimes', 'nullable', 'exists:rooms,id'],
            'procedure_id' => ['sometimes', 'exists:procedures,id'],
            'priority' => ['sometimes', 'nullable', Rule::in(['emergency', 'urgent', 'scheduled'])],
            'diagnosis' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'expected_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'anesthesia_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requested_by_doctor_id' => ['sometimes', 'nullable', 'integer'],
            'scheduled_at' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
