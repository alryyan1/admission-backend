<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVitalSignRequest extends FormRequest
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
            'recorded_at' => ['nullable', 'date'],
            'temperature' => ['nullable', 'numeric', 'between:30,45'],
            'pulse' => ['nullable', 'integer', 'between:0,300'],
            'respiration_rate' => ['nullable', 'integer', 'between:0,120'],
            'blood_pressure' => ['nullable', 'string', 'max:15'],
            'oxygen_saturation' => ['nullable', 'integer', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
