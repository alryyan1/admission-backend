<?php

namespace Database\Factories;

use App\Models\Operation;
use App\Models\OperationSupply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationSupply>
 */
class OperationSupplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operation_id' => Operation::factory(),
            'name' => fake()->word(),
            'quantity' => fake()->numberBetween(1, 5),
            'unit' => 'piece',
        ];
    }
}
