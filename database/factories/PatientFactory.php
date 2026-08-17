<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'jawda_patient_id' => null,
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'gender' => fake()->randomElement(['male', 'female']),
            'age_year' => fake()->numberBetween(1, 90),
            'age_month' => null,
            'age_day' => null,
            'address' => fake()->address(),
            'is_local_only' => true,
        ];
    }
}
