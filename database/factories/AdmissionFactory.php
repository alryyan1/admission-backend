<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admission>
 */
class AdmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'bed_id' => Bed::factory(),
            'admitting_doctor_id' => null,
            'admission_date' => now(),
            'status' => 'admitted',
            'diagnosis' => fake()->sentence(4),
            'admission_notes' => fake()->sentence(),
        ];
    }
}
