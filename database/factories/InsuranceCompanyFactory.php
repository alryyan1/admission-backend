<?php

namespace Database\Factories;

use App\Models\InsuranceCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InsuranceCompany>
 */
class InsuranceCompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['التأمين الوطني', 'التعاونية', 'بوبا', 'ميد غلف', 'الأهلية']),
            'phone' => fake()->numerify('05########'),
        ];
    }
}
