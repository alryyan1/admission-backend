<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionDeposit>
 */
class AdmissionDepositFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory(),
            'amount' => fake()->randomElement([50000, 100000, 200000]),
            'payment_method_id' => PaymentMethod::factory(),
            'paid_at' => now(),
        ];
    }
}
