<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShortStayAssessmentRequest extends FormRequest
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
            'blood_pressure' => ['nullable', 'string', 'max:15'],
            'oxygen_saturation' => ['nullable', 'integer', 'between:0,100'],
            'pulse' => ['nullable', 'integer', 'between:0,300'],
            'respiration_rate' => ['nullable', 'integer', 'between:0,120'],
            'pmh' => ['nullable', 'string', 'max:4000'],
            'chief_complaint' => ['nullable', 'string', 'max:4000'],
            'plan' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
