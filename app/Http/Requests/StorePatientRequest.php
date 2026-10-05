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
        ];
    }
}
