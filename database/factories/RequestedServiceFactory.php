<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\RequestedService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestedService>
 */
class RequestedServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory(),
            'name' => fake()->randomElement(['أشعة سينية', 'تحليل دم', 'ضمادة جراحية', 'محلول وريدي']),
            'quantity' => fake()->numberBetween(1, 3),
            'unit_price' => fake()->randomElement([10000, 15000, 25000]),
        ];
    }
}
