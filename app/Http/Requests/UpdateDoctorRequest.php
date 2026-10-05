<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'specialist_id' => ['sometimes', 'nullable', 'integer', 'exists:specialists,id'],
            'role_id' => ['sometimes', 'required', 'integer', 'exists:team_roles,id'],
            'jawda_doctor_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::unique('doctors', 'jawda_doctor_id')->ignore($this->route('doctor')),
            ],
        ];
    }
}
