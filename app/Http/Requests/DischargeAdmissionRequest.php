<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class DischargeAdmissionRequest extends FormRequest
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
            'discharge_date' => ['nullable', 'date', function (string $attribute, mixed $value, \Closure $fail): void {
                $admission = $this->route('admission');

                if ($admission && $value && Carbon::parse($value)->lt($admission->admission_date)) {
                    $fail('تاريخ الخروج لا يمكن أن يكون قبل تاريخ الدخول.');
                }
            }],
            'discharge_summary' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
