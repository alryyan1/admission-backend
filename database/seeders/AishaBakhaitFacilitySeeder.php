<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Ward;
use Illuminate\Database\Seeder;

/**
 * Seeds the real physical structure of مستشفى عائشة بخيت (Aisha Bakhait hospital):
 * 4 floors, each with one or more wards, each ward with one or more rooms,
 * each room with its beds (or delivery chairs).
 */
class AishaBakhaitFacilitySeeder extends Seeder
{
    public function run(): void
    {
        $floors = [
            [
                'name' => 'الأرضي',
                'wards' => [
                    [
                        'name' => 'الطوارئ - الاقامات القصيرة',
                        'rooms' => [
                            ['room_number' => '1', 'room_type' => 'normal', 'capacity' => 11, 'is_short_stay' => true, 'beds_count' => 11],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'الطابق الأول - الجزء الغربي',
                'wards' => [
                    [
                        'name' => 'العناية المكثفة',
                        'rooms' => [
                            ['room_number' => '1', 'room_type' => 'normal', 'capacity' => 8, 'beds_count' => 8],
                        ],
                    ],
                    [
                        'name' => 'العناية الوسيطة',
                        'rooms' => [
                            ['room_number' => '1', 'room_type' => 'normal', 'capacity' => 2, 'beds_count' => 2],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'الطابق الثاني',
                'wards' => [
                    [
                        'name' => 'الحضانة وعنبر الولادة الطبيعية',
                        'rooms' => [
                            ['room_number' => '1', 'room_type' => 'normal', 'capacity' => 4, 'beds_count' => 4],
                        ],
                    ],
                    [
                        'name' => 'غرفة الولادة الطبيعية',
                        'rooms' => [
                            ['room_number' => '1', 'room_type' => 'normal', 'capacity' => 4, 'beds_count' => 4, 'unit_type' => 'chair'],
                        ],
                    ],
                    [
                        'name' => 'مجمع العمليات والركفري - الجزء الشرقي',
                        'rooms' => [
                            ['room_number' => '1', 'room_type' => 'normal', 'capacity' => 0, 'beds_count' => 0],
                        ],
                    ],
                    [
                        'name' => 'غرف خاصة',
                        'rooms' => [
                            ['room_number' => '1', 'room_type' => 'normal', 'capacity' => 2, 'price_per_day' => 250000, 'beds_count' => 2],
                            ['room_number' => '2', 'room_type' => 'normal', 'capacity' => 2, 'price_per_day' => 250000, 'beds_count' => 2],
                            ['room_number' => '3', 'room_type' => 'normal', 'capacity' => 2, 'price_per_day' => 250000, 'beds_count' => 2],
                            ['room_number' => 'VIP', 'room_type' => 'vip', 'capacity' => 2, 'price_per_day' => 300000, 'beds_count' => 2],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'الطابق الثالث',
                'wards' => [
                    [
                        'name' => 'غرف خاصة',
                        'rooms' => [
                            ['room_number' => '1', 'room_type' => 'normal', 'capacity' => 2, 'price_per_day' => 250000, 'beds_count' => 2],
                            ['room_number' => '2', 'room_type' => 'normal', 'capacity' => 2, 'price_per_day' => 250000, 'beds_count' => 2],
                            ['room_number' => '3', 'room_type' => 'normal', 'capacity' => 2, 'price_per_day' => 250000, 'beds_count' => 2],
                            ['room_number' => '4', 'room_type' => 'normal', 'capacity' => 2, 'price_per_day' => 250000, 'beds_count' => 2],
                            ['room_number' => 'VIP', 'room_type' => 'vip', 'capacity' => 2, 'price_per_day' => 300000, 'beds_count' => 2],
                        ],
                    ],
                    [
                        'name' => 'عنبر أطفال',
                        'gender' => 'children',
                        'rooms' => [
                            ['room_number' => '1', 'room_type' => 'normal', 'capacity' => 0, 'beds_count' => 0],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($floors as $floorData) {
            $wardConfigs = $floorData['wards'];
            unset($floorData['wards']);

            $floor = Floor::firstOrCreate(
                ['name' => $floorData['name']],
                ['status' => true]
            );

            foreach ($wardConfigs as $wardData) {
                $roomConfigs = $wardData['rooms'];
                unset($wardData['rooms']);

                $ward = Ward::firstOrCreate(
                    ['floor_id' => $floor->id, 'name' => $wardData['name']],
                    ['gender' => $wardData['gender'] ?? null, 'status' => true]
                );

                foreach ($roomConfigs as $rc) {
                    $bedsCount = $rc['beds_count'];
                    $unitType = $rc['unit_type'] ?? 'bed';
                    unset($rc['beds_count'], $rc['unit_type']);

                    $room = Room::firstOrCreate(
                        ['ward_id' => $ward->id, 'room_number' => $rc['room_number']],
                        [
                            'room_type' => $rc['room_type'],
                            'capacity' => $rc['capacity'],
                            'price_per_day' => $rc['price_per_day'] ?? null,
                            'is_short_stay' => $rc['is_short_stay'] ?? false,
                            'price_12_hours' => null,
                            'price_24_hours' => null,
                            'status' => true,
                        ]
                    );

                    $existingBeds = $room->beds()->count();
                    for ($n = $existingBeds + 1; $n <= $bedsCount; $n++) {
                        Bed::firstOrCreate(
                            ['room_id' => $room->id, 'bed_number' => (string) $n],
                            ['unit_type' => $unitType, 'status' => 'available']
                        );
                    }
                }
            }
        }
    }
}
