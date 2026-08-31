<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\Doctor;
use App\Models\Operation;
use App\Models\Procedure;
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
            'procedure_id' => Procedure::factory(),
            'scheduled_at' => now()->addDay(),
        ];
    }
}
