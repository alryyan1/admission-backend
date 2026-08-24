<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOperationTeamMemberRequest extends FormRequest
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
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'name' => ['nullable', 'string', 'max:255', 'required_without:doctor_id'],
            'role_id' => ['required', 'integer', 'exists:team_roles,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
