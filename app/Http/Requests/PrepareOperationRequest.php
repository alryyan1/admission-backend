<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrepareOperationRequest extends FormRequest
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
            'consent_obtained' => ['required', 'boolean'],
            'fasting_confirmed' => ['required', 'boolean'],
            'site_marked' => ['required', 'boolean'],
            'preop_vitals_checked' => ['required', 'boolean'],
            'preop_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
