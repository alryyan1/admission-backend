<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\Doctor;
use App\Models\Operation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Operation>
 */
class OperationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory(),
            'surgeon_id' => Doctor::factory(),
            'operation_room_id' => null,
            'procedure_name' => fake()->sentence(3),
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ];
    }
}
