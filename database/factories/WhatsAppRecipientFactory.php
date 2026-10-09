<?php

namespace Database\Factories;

use App\Models\WhatsAppRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppRecipient>
 */
class WhatsAppRecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '0'.fake()->numerify('#########'),
            'label' => fake()->name(),
        ];
    }
}
