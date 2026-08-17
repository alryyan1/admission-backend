<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ward_id' => Ward::factory(),
            'room_number' => (string) fake()->unique()->numberBetween(1, 999),
            'room_type' => fake()->randomElement(['normal', 'vip']),
            'capacity' => fake()->numberBetween(1, 6),
            'price_per_day' => fake()->randomElement([50000, 100000, 150000]),
            'is_short_stay' => false,
            'price_12_hours' => null,
            'price_24_hours' => null,
            'status' => true,
        ];
    }
}
