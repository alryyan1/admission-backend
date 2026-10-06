<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignAdmissionBedRequest extends FormRequest
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
            'bed_id' => [
                'required',
                Rule::exists('beds', 'id')->where('status', 'available'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bed_id.required' => 'يجب اختيار السرير.',
            'bed_id.exists' => 'السرير غير متاح للتنويم حالياً.',
        ];
    }
}
