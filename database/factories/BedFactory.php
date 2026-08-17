<?php

namespace Database\Factories;

use App\Models\Bed;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bed>
 */
class BedFactory extends Factory
{
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'bed_number' => (string) fake()->unique()->numberBetween(1, 9999),
            'unit_type' => 'bed',
            'status' => 'available',
        ];
    }
}
