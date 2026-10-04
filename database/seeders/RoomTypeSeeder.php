<?php

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        $roomTypes = [
            'normal' => 'عادية',
            'vip' => 'خاصة',
            'nursery' => 'حضانة',
            'ward' => 'عنبر',
        ];

        foreach ($roomTypes as $code => $name) {
            RoomType::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
