<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\RequestedService;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionRevenueReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_room_revenue_grouped_by_room_type(): void
    {
        $user = User::factory()->create();

        $vipRoom = Room::factory()->create(['room_type' => 'vip']);
        $vipBed = Bed::factory()->create(['room_id' => $vipRoom->id]);
        $vipAdmission = Admission::factory()->create(['bed_id' => $vipBed->id, 'admission_date' => '2026-02-10']);
        RequestedService::factory()->create([
            'admission_id' => $vipAdmission->id,
            'name' => RequestedService::AccommodationFeeName,
            'quantity' => 2,
            'unit_price' => 1000,
        ]);

        $normalRoom = Room::factory()->create(['room_type' => 'normal']);
        $normalBed = Bed::factory()->create(['room_id' => $normalRoom->id]);
        $normalAdmission = Admission::factory()->create(['bed_id' => $normalBed->id, 'admission_date' => '2026-02-15']);
        RequestedService::factory()->create([
            'admission_id' => $normalAdmission->id,
            'name' => RequestedService::AccommodationFeeName,
            'quantity' => 3,
            'unit_price' => 500,
        ]);

        // Outside the requested range, must not be counted.
        $outOfRangeAdmission = Admission::factory()->create(['bed_id' => $vipBed->id, 'admission_date' => '2026-03-01']);
        RequestedService::factory()->create([
            'admission_id' => $outOfRangeAdmission->id,
            'name' => RequestedService::AccommodationFeeName,
            'quantity' => 99,
            'unit_price' => 99,
        ]);

        // Has no accommodation fee at all, so it shouldn't appear in a room-revenue report.
        Admission::factory()->create(['admission_date' => '2026-02-12']);

        $response = $this->actingAs($user, 'sanctum')->getJson(
            '/api/reports/admission-revenue?from=2026-02-01&to=2026-02-28'
        );

        $response->assertOk();
        $response->assertJsonPath('summary.admissions_count', 2);
        $response->assertJsonPath('summary.room_revenue', 3500);
        $response->assertJsonPath('by_room_type.0.room_type_code', 'vip');
        $response->assertJsonPath('by_room_type.0.total', 2000);
        $response->assertJsonPath('by_room_type.1.room_type_code', 'normal');
        $response->assertJsonPath('by_room_type.1.total', 1500);
    }

    public function test_it_is_forbidden_for_roles_outside_admin_and_cashier(): void
    {
        $nurse = User::factory()->role('nurse')->create();

        $this->actingAs($nurse, 'sanctum')->getJson('/api/reports/admission-revenue')->assertForbidden();
    }
}
