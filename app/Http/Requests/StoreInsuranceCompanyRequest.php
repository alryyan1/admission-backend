<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsuranceCompanyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:insurance_companies,name'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'coverage_percentage' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم شركة التأمين مطلوب',
            'name.unique' => 'اسم شركة التأمين مسجل مسبقاً',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'coverage_percentage.between' => 'نسبة التحمل يجب أن تكون بين 0 و 100',
        ];
    }
}
