<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * @var array<int, array{ar: string, en: string}>
     */
    private array $services = [
        ['ar' => 'ملف', 'en' => 'File Opening'],
        ['ar' => 'إقامة قصيرة', 'en' => 'Short Stay'],
        ['ar' => 'إقامة طويلة', 'en' => 'Long Stay'],
    ];

    public function run(): void
    {
        $category = ServiceCategory::firstOrCreate(['name' => 'خدمات التنويم']);

        foreach ($this->services as $service) {
            Service::updateOrCreate(
                ['name_ar' => $service['ar']],
                ['category_id' => $category->id, 'name_en' => $service['en'], 'is_active' => true],
            );
        }
    }
}
