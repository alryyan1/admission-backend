<?php

namespace Database\Factories;

use App\Models\Floor;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ward>
 */
class WardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'floor_id' => Floor::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'gender' => null,
            'status' => true,
        ];
    }
}
