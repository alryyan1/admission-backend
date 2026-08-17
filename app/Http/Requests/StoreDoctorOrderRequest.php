<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorOrderRequest extends FormRequest
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
            'order_text' => ['required', 'string', 'max:255'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:100'],
        ];
    }
}
