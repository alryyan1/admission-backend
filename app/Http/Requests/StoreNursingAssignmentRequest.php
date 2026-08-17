<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNursingAssignmentRequest extends FormRequest
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
            'nurse_id' => ['required', 'exists:users,id'],
            'bed_id' => ['required', 'exists:beds,id'],
            'shift_start' => ['required', 'date'],
            'shift_end' => ['required', 'date', 'after:shift_start'],
        ];
    }
}
