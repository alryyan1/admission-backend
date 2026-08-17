<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\VitalSign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VitalSign>
 */
class VitalSignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory(),
            'recorded_at' => now(),
            'temperature' => fake()->randomFloat(1, 36, 39.5),
            'pulse' => fake()->numberBetween(60, 110),
            'respiration_rate' => fake()->numberBetween(12, 24),
            'blood_pressure' => fake()->numberBetween(90, 140).'/'.fake()->numberBetween(60, 90),
            'oxygen_saturation' => fake()->numberBetween(92, 100),
        ];
    }
}
