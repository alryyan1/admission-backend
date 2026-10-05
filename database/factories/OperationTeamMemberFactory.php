<?php

namespace Database\Factories;

use App\Models\Operation;
use App\Models\OperationTeamMember;
use App\Models\TeamRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationTeamMember>
 */
class OperationTeamMemberFactory extends Factory
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
            'name' => fake()->name(),
            'role_id' => TeamRole::factory(),
        ];
    }
}
