<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'age_year' => ['nullable', 'integer', 'min:0'],
            'age_month' => ['nullable', 'integer', 'min:0', 'max:11'],
            'age_day' => ['nullable', 'integer', 'min:0', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'insurance_company_id' => ['sometimes', 'nullable', 'integer', 'exists:insurance_companies,id'],
            'insurance_card_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_address' => ['nullable', 'string', 'max:255'],
            'blood_type' => ['nullable', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'allergies' => ['nullable', 'string'],
            'chronic_diseases' => ['nullable', 'string'],
            'current_medications' => ['nullable', 'string'],
            'past_surgeries' => ['nullable', 'string'],
            'medical_history' => ['nullable', 'string'],
            'medical_notes' => ['nullable', 'string'],
        ];
    }
}
