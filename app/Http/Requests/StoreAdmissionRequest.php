<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionRequest extends FormRequest
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
            'patient_id' => ['required', 'exists:patients,id'],
            'bed_id' => [
                'required',
                Rule::exists('beds', 'id')->where('status', 'available'),
            ],
            'admitting_doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'referred_by_doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'admission_date' => ['nullable', 'date'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'admission_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'bed_id.exists' => 'السرير غير متاح للتنويم حالياً.',
        ];
    }
}
