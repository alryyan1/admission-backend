<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdmissionRequest extends FormRequest
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
            'diagnosis' => ['sometimes', 'nullable', 'string', 'max:255'],
            'admission_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
