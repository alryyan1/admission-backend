<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_admission_services_under_a_category(): void
    {
        $this->seed(ServiceSeeder::class);

        $category = ServiceCategory::where('name', 'خدمات التنويم')->first();
        $this->assertNotNull($category);

        foreach (['ملف', 'إقامة قصيرة', 'إقامة طويلة'] as $name) {
            $this->assertDatabaseHas('services', [
                'name_ar' => $name,
                'category_id' => $category->id,
                'is_active' => true,
            ]);
        }
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(ServiceSeeder::class);
        $this->seed(ServiceSeeder::class);

        $this->assertSame(1, ServiceCategory::where('name', 'خدمات التنويم')->count());
        $this->assertSame(3, Service::whereIn('name_ar', ['ملف', 'إقامة قصيرة', 'إقامة طويلة'])->count());
    }
}
