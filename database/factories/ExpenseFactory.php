<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_date' => fake()->date(),
            'category' => fake()->randomElement(['رواتب', 'إيجار', 'صيانة']),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'payment_method_id' => null,
            'recorded_by_id' => User::factory(),
        ];
    }
}
