<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteOperationRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:2000'],
            'findings' => ['nullable', 'string', 'max:4000'],
            'complications' => ['nullable', 'string', 'max:4000'],
            'blood_loss_ml' => ['nullable', 'integer', 'min:0'],
            'outcome' => ['nullable', 'string', 'max:255'],
            'report_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
