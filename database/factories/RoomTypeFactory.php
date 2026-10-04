<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => RoomType::generateUniqueCode(),
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
