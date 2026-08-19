<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\Bed;
use App\Models\Invoice;
use App\Models\RequestedService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StatisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_occupancy_reports_bed_counts_and_rate(): void
    {
        $user = User::factory()->create();
        $occupiedBed = Bed::factory()->create(['status' => 'occupied']);
        Bed::factory()->create(['status' => 'available']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/statistics/occupancy');

        $response->assertOk();
        $response->assertJsonPath('summary.total_beds', 2);
        $response->assertJsonPath('summary.occupied_beds', 1);
        $response->assertJsonPath('summary.available_beds', 1);
        $response->assertJsonPath('summary.occupancy_rate', 50);
        $response->assertJsonFragment(['ward_id' => $occupiedBed->room->ward_id]);
    }

    public function test_admissions_statistics_are_scoped_to_the_requested_range(): void
    {
        $user = User::factory()->create();
        Admission::factory()->create(['admission_date' => now(), 'status' => 'admitted']);
        Admission::factory()->create(['admission_date' => now()->subDays(60), 'status' => 'discharged']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/statistics/admissions');

        $response->assertOk();
        $response->assertJsonPath('active_admissions', 1);
        $response->assertJsonPath('status_counts.admitted', 1);
        $response->assertJsonMissingPath('status_counts.discharged');
    }

    public function test_financials_statistics_sum_paid_invoices_and_deposits(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        Invoice::factory()->for($admission)->create(['status' => 'paid', 'total' => 15000, 'paid_at' => now()]);
        Invoice::factory()->for($admission)->create(['status' => 'issued', 'total' => 5000]);
        AdmissionDeposit::factory()->for($admission)->create(['amount' => 3000, 'method' => 'cash', 'paid_at' => now()]);
        RequestedService::factory()->for($admission)->create(['quantity' => 2, 'unit_price' => 1000]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/statistics/financials');

        $response->assertOk();
        $response->assertJsonPath('paid_total', 15000);
        $response->assertJsonPath('outstanding_total', 5000);
        $response->assertJsonPath('deposits_total', 3000);
        $response->assertJsonPath('services_total', 2000);
    }

    public function test_doctors_and_services_statistics_rank_top_entries(): void
    {
        $user = User::factory()->create();
        Http::fake([
            '*/all-doctors*' => Http::response([
                'data' => [
                    ['id' => 501, 'name' => 'د. أحمد سالم', 'specialist_name' => 'باطنية'],
                ],
            ], 200),
        ]);
        $admission = Admission::factory()->create(['admitting_doctor_id' => 501, 'admission_date' => now()]);
        RequestedService::factory()->for($admission)->create(['name' => 'أشعة', 'quantity' => 2, 'unit_price' => 5000]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/statistics/doctors-services');

        $response->assertOk();
        $response->assertJsonFragment(['id' => 501, 'name' => 'د. أحمد سالم', 'admissions_count' => 1]);
        $response->assertJsonFragment(['name' => 'أشعة', 'total_quantity' => 2]);
    }
}
