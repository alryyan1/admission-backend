<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIntakeOutputRecordRequest extends FormRequest
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
            'recorded_at' => ['nullable', 'date'],
            'feeding' => ['nullable', 'numeric', 'between:-99999.99,99999.99'],
            'iv_fluid' => ['nullable', 'numeric', 'between:-99999.99,99999.99'],
            'maintenance' => ['nullable', 'numeric', 'between:-99999.99,99999.99'],
            'urine_output' => ['nullable', 'numeric', 'between:-99999.99,99999.99'],
            'insensible_losses' => ['nullable', 'numeric', 'between:-99999.99,99999.99'],
            'total_intake' => ['nullable', 'numeric', 'between:-99999.99,99999.99'],
            'total_output' => ['nullable', 'numeric', 'between:-99999.99,99999.99'],
            'balance' => ['nullable', 'numeric', 'between:-99999.99,99999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
