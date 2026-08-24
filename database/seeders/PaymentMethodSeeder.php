<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            'نقدي',
            'بنكك',
            'اوكاش',
            'فوري'
        ];

        foreach ($methods as $name) {
            PaymentMethod::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
