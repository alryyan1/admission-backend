<?php

namespace Database\Factories;

use App\Models\Bed;
use App\Models\NursingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NursingAssignment>
 */
class NursingAssignmentFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 day', 'now');

        return [
            'nurse_id' => User::factory()->role('nurse'),
            'bed_id' => Bed::factory(),
            'shift_start' => $start,
            'shift_end' => (clone $start)->modify('+8 hours'),
        ];
    }
}
