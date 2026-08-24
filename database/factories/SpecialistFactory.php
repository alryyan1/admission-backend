<?php

namespace Database\Factories;

use App\Models\Specialist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Specialist>
 */
class SpecialistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['باطنية', 'أطفال', 'جراحة عامة', 'عظام', 'نساء وتوليد', 'قلبية', 'مسالك بولية']),
        ];
    }
}
