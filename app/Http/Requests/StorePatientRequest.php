<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'age_year' => ['nullable', 'integer', 'min:0'],
            'age_month' => ['nullable', 'integer', 'min:0', 'max:11'],
            'age_day' => ['nullable', 'integer', 'min:0', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'insurance_company_id' => ['nullable', 'integer', 'exists:insurance_companies,id'],
            'insurance_card_number' => ['nullable', 'string', 'max:100'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'guardian_relationship_id' => ['nullable', 'integer', 'exists:guardian_relationships,id'],
            'admitting_doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'referred_by_doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
