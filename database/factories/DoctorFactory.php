<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\TeamRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'jawda_doctor_id' => null,
            'name' => 'د. '.fake()->name(),
            'specialist' => fake()->randomElement(['باطنية', 'أطفال', 'جراحة عامة', 'عظام', 'نساء وتوليد', null]),
            'role_id' => TeamRole::factory(),
        ];
    }
}
