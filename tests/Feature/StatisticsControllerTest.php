<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\Bed;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Operation;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\RequestedService;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        AdmissionDeposit::factory()->for($admission)->create(['amount' => 3000, 'paid_at' => now()]);
        RequestedService::factory()->for($admission)->create(['name' => 'تحليل دم', 'quantity' => 2, 'unit_price' => 1000]);
        RequestedService::factory()->for($admission)->create(['name' => RequestedService::AccommodationFeeName, 'quantity' => 3, 'unit_price' => 5000]);
        Operation::factory()->for($admission)->create(['price' => 40000]);
        Operation::factory()->for($admission)->create(['price' => null]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/statistics/financials');

        $response->assertOk();
        $response->assertJsonPath('paid_total', 15000);
        $response->assertJsonPath('outstanding_total', 5000);
        $response->assertJsonPath('deposits_total', 3000);
        $response->assertJsonPath('services_total', 2000);
        $response->assertJsonPath('rooms_total', 15000);
        $response->assertJsonPath('operations_total', 40000);
        $response->assertJsonPath('charges_total', 57000);
    }

    public function test_operations_statistics_report_count_and_revenue_in_range(): void
    {
        $user = User::factory()->create();
        Operation::factory()->create(['scheduled_at' => now(), 'price' => 30000]);
        Operation::factory()->create(['scheduled_at' => now(), 'price' => 20000]);
        Operation::factory()->create(['scheduled_at' => now()->subDays(90), 'price' => 99000]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/statistics/operations');

        $response->assertOk();
        $response->assertJsonPath('total_in_range', 2);
        $response->assertJsonPath('revenue_in_range', 50000);
    }

    public function test_doctors_and_services_statistics_rank_top_entries(): void
    {
        $user = User::factory()->create();
        $doctor = Doctor::factory()->create(['name' => 'د. أحمد سالم', 'specialist_id' => Specialist::factory()->create(['name' => 'باطنية'])->id]);
        $patient = Patient::factory()->create(['admitting_doctor_id' => $doctor->id]);
        $admission = Admission::factory()->create(['patient_id' => $patient->id, 'admission_date' => now()]);
        RequestedService::factory()->for($admission)->create(['name' => 'أشعة', 'quantity' => 2, 'unit_price' => 5000]);
        $procedure = Procedure::factory()->create(['name_ar' => 'استئصال المرارة']);
        Operation::factory()->for($admission)->create(['procedure_id' => $procedure->id, 'price' => 120000]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/statistics/doctors-services');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $doctor->id, 'name' => 'د. أحمد سالم', 'admissions_count' => 1]);
        $response->assertJsonFragment(['name' => 'أشعة', 'total_quantity' => 2]);
        $response->assertJsonFragment(['name' => 'استئصال المرارة', 'total_quantity' => 1, 'total_revenue' => 120000]);
    }
}
