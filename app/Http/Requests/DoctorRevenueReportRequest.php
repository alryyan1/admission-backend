<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DoctorRevenueReportRequest extends FormRequest
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
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'doctor_role' => ['required', 'in:admitting,referring'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doctor_id.required' => 'يجب اختيار الطبيب',
            'doctor_id.exists' => 'الطبيب المحدد غير موجود',
            'doctor_role.required' => 'يجب تحديد نوع الطبيب (معالج / محول)',
            'doctor_role.in' => 'نوع الطبيب غير صالح',
            'to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
        ];
    }
}
