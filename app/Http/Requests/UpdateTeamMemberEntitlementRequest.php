<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberEntitlementRequest extends FormRequest
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
            'entitlement_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', Rule::exists('payment_methods', 'id')],
            'entitlement_paid_at' => ['nullable', 'date'],
            'name' => ['nullable', 'string', 'max:255'],
            'doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')],
        ];
    }
}
