<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\DoctorOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorOrder>
 */
class DoctorOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory(),
            'order_text' => fake()->sentence(3),
            'frequency' => fake()->randomElement(['مرة يومياً', 'مرتين يومياً', 'كل 8 ساعات']),
            'route' => fake()->randomElement(['فموي', 'وريدي', 'عضلي']),
            'status' => 'active',
        ];
    }
}
