<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'doctor_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:255', 'required_without:doctor_id'],
            'role' => [
                'required',
                Rule::in(['surgeon', 'assistant_surgeon', 'anesthesiologist', 'scrub_nurse', 'circulating_nurse', 'technician', 'other']),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
