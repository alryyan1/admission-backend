<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50000, 300000);

        return [
            'admission_id' => Admission::factory(),
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'status' => 'issued',
            'issued_at' => now(),
        ];
    }
}
