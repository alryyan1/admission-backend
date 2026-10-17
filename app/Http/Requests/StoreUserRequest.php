<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', Rule::in(['admin', 'doctor', 'nurse', 'admission_clerk', 'cashier'])],
            'allowed_pages' => ['nullable', 'array'],
            'allowed_pages.*' => ['string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(User::AVAILABLE_PERMISSIONS)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
