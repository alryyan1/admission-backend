<?php

namespace Database\Factories;

use App\Models\DoctorOrder;
use App\Models\TreatmentDose;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentDose>
 */
class TreatmentDoseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'doctor_order_id' => DoctorOrder::factory(),
            'administered_at' => now(),
            'status' => 'given',
        ];
    }
}
