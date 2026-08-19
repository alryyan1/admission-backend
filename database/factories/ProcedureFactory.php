<?php

namespace Database\Factories;

use App\Models\Procedure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Procedure>
 */
class ProcedureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => null,
            'name_ar' => fake()->sentence(3),
            'name_en' => null,
            'type' => null,
            'description' => null,
            'is_active' => true,
        ];
    }
}
