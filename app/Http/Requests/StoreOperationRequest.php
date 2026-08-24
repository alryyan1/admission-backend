<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperationRequest extends FormRequest
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
            'surgeon_id' => ['required', 'integer', 'exists:doctors,id'],
            'operation_room_id' => ['nullable', 'exists:rooms,id'],
            'procedure_id' => ['required', 'exists:procedures,id'],
            'priority' => ['nullable', Rule::in(['emergency', 'urgent', 'scheduled'])],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'expected_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'anesthesia_type' => ['nullable', 'string', 'max:255'],
            'requested_by_doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
