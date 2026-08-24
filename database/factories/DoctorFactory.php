<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Specialist;
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
            'specialist_id' => fake()->boolean(80) ? Specialist::factory() : null,
            'role_id' => TeamRole::factory(),
        ];
    }
}
