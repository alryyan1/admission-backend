<?php

namespace Database\Factories;

use App\Models\GuardianRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuardianRelationship>
 */
class GuardianRelationshipFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Deliberately excludes the four defaults the create_guardian_relationships_table
            // migration seeds (الأب, الأم, الزوجة, الزوج), so factory-made rows never collide
            // with them under the unique name constraint.
            'name' => fake()->unique()->randomElement(['الأخ', 'الأخت', 'الابن', 'الابنة']),
        ];
    }
}
