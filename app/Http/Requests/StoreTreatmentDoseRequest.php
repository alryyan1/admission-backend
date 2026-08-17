<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTreatmentDoseRequest extends FormRequest
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
            'administered_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['given', 'missed', 'refused'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
