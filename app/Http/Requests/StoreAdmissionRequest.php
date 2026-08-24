<?php

namespace App\Http\Requests;

use App\Models\Bed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'admission_date' => ['nullable', 'date'],
            'admission_duration_hours' => ['nullable', 'integer', Rule::in([12, 24])],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'admission_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $bed = Bed::with('room')->find($this->input('bed_id'));

            if (! $bed || ! $bed->room->is_short_stay) {
                return;
            }

            if (! in_array($this->input('admission_duration_hours'), [12, 24], true)) {
                $validator->errors()->add(
                    'admission_duration_hours',
                    'هذا السرير في غرفة إقامات قصيرة، يجب تحديد مدة الإقامة (12 أو 24 ساعة).'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'bed_id.exists' => 'السرير غير متاح للتنويم حالياً.',
        ];
    }
}
