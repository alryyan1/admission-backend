<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($this->route('user')),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'role' => ['sometimes', 'required', 'string', Rule::in(['admin', 'doctor', 'nurse', 'admission_clerk', 'cashier'])],
            'allowed_pages' => ['sometimes', 'nullable', 'array'],
            'allowed_pages.*' => ['string'],
            'permissions' => ['sometimes', 'nullable', 'array'],
            'permissions.*' => ['string', Rule::in(User::AVAILABLE_PERMISSIONS)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
