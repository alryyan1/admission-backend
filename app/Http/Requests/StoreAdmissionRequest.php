<?php

namespace App\Http\Requests;

use App\Models\Admission;
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
            'admission_date' => ['nullable', 'date'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'admission_notes' => ['nullable', 'string', 'max:2000'],
            'entry_type' => ['nullable', Rule::in(array_keys(Admission::ENTRY_TYPES))],
            'referring_hospital_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->input('entry_type') === Admission::ENTRY_TYPE_HOSPITAL_TRANSFER),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bed_id.exists' => 'السرير غير متاح للتنويم حالياً.',
            'entry_type.in' => 'نوع الدخول غير صالح.',
            'referring_hospital_name.required' => 'اسم المستشفى المحوِّل مطلوب عند اختيار تحويل من مستشفى آخر.',
        ];
    }
}
