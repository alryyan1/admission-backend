<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorRequest extends FormRequest
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
            'specialist_id' => ['nullable', 'integer', 'exists:specialists,id'],
            'role_id' => ['required', 'integer', 'exists:team_roles,id'],
            'jawda_doctor_id' => ['nullable', 'integer', 'unique:doctors,jawda_doctor_id'],
        ];
    }
}
