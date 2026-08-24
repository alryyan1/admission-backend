<?php

namespace Database\Factories;

use App\Models\TeamRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamRole>
 */
class TeamRoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => null,
            'name' => fake()->unique()->jobTitle(),
            'sort_order' => 0,
            'is_protected' => false,
        ];
    }
}
