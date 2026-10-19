<?php

namespace App\Http\Requests;

use App\Models\Operation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayOperationRequest extends FormRequest
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
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                function (string $attribute, mixed $value, callable $fail): void {
                    $operation = $this->route('operation');

                    if (! $operation instanceof Operation || $operation->price === null) {
                        return;
                    }

                    // Epsilon absorbs decimal rounding noise so paying exactly what's left is never rejected.
                    if ((float) $value > $operation->remaining_amount + 0.005) {
                        $fail('المبلغ أكبر من المتبقي على العملية ('.number_format($operation->remaining_amount, 2).').');
                    }
                },
            ],
            'payment_method_id' => ['nullable', Rule::exists('payment_methods', 'id')],
        ];
    }
}
