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
            'emergency_contact_name' => fake()->optional()->name(),
            'emergency_contact_relationship' => fake()->optional()->randomElement(['زوج', 'زوجة', 'ابن', 'ابنة', 'أخ', 'أخت', 'والد', 'والدة']),
            'emergency_contact_phone' => fake()->optional()->phoneNumber(),
            'emergency_contact_address' => fake()->optional()->address(),
            'blood_type' => fake()->optional()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            'allergies' => fake()->optional()->sentence(),
            'chronic_diseases' => fake()->optional()->sentence(),
            'current_medications' => fake()->optional()->sentence(),
            'past_surgeries' => fake()->optional()->sentence(),
            'medical_history' => fake()->optional()->paragraph(),
            'medical_notes' => fake()->optional()->sentence(),
        ];
    }
}
