<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => null,
            'name_ar' => fake()->unique()->sentence(3),
            'name_en' => null,
            'price' => fake()->randomElement([5000, 10000, 25000]),
            'is_active' => true,
        ];
    }
}
