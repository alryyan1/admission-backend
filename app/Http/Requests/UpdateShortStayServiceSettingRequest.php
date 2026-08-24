<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShortStayServiceSettingRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'service_12h_id' => ['nullable', 'exists:services,id'],
            'service_24h_id' => ['nullable', 'exists:services,id'],
        ];
    }
}
