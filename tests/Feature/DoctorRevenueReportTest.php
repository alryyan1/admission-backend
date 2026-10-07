<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Doctor;
use App\Models\Operation;
use App\Models\Patient;
use App\Models\RequestedService;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorRevenueReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_sums_room_services_and_operations_revenue_for_the_treating_doctor(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $doctor = Doctor::factory()->create(['name' => 'د. أحمد']);
        $otherDoctor = Doctor::factory()->create();

        $vipType = RoomType::factory()->create(['code' => 'vip', 'name' => 'جناح VIP']);
        $vipRoom = Room::factory()->create(['room_type' => $vipType->code, 'price_per_day' => 100]);
        $vipBed = Bed::factory()->create(['room_id' => $vipRoom->id]);

        $patient = Patient::factory()->create(['admitting_doctor_id' => $doctor->id]);
        $admission = Admission::factory()->create([
            'patient_id' => $patient->id,
            'bed_id' => $vipBed->id,
            'admission_date' => '2026-10-05 10:00:00',
        ]);

        RequestedService::factory()->create([
            'admission_id' => $admission->id,
            'name' => RequestedService::AccommodationFeeName,
            'quantity' => 3,
            'unit_price' => 100,
        ]);
        RequestedService::factory()->create([
            'admission_id' => $admission->id,
            'name' => 'تحليل دم',
            'quantity' => 2,
            'unit_price' => 50,
        ]);
        Operation::factory()->create([
            'admission_id' => $admission->id,
            'price' => 500,
        ]);

        $otherPatient = Patient::factory()->create(['admitting_doctor_id' => $otherDoctor->id]);
        Admission::factory()->create([
            'patient_id' => $otherPatient->id,
            'admission_date' => '2026-10-06 10:00:00',
        ]);

        $response = $this->actingAs($cashier, 'sanctum')->getJson(
            "/api/reports/doctor-revenue?doctor_id={$doctor->id}&doctor_role=admitting&from=2026-10-01&to=2026-10-31"
        );

        $response->assertOk()
            ->assertJsonPath('doctor.name', 'د. أحمد')
            ->assertJsonPath('doctor_role', 'admitting')
            ->assertJsonPath('summary.patients_count', 1)
            ->assertJsonPath('summary.admissions_count', 1)
            ->assertJsonPath('summary.room_revenue', 300)
            ->assertJsonPath('summary.services_revenue', 100)
            ->assertJsonPath('summary.operations_revenue', 500)
            ->assertJsonPath('summary.total_revenue', 900)
            ->assertJsonCount(1, 'room_revenue_by_type')
            ->assertJsonPath('room_revenue_by_type.0.room_type_name', 'جناح VIP')
            ->assertJsonPath('room_revenue_by_type.0.total', 300)
            ->assertJsonCount(1, 'admissions')
            ->assertJsonPath('admissions.0.room_type_name', 'جناح VIP');
    }

    public function test_report_filters_by_referring_doctor_when_role_is_referring(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $referringDoctor = Doctor::factory()->create();

        $patient = Patient::factory()->create(['referred_by_doctor_id' => $referringDoctor->id]);
        Admission::factory()->create([
            'patient_id' => $patient->id,
            'admission_date' => '2026-10-05 10:00:00',
        ]);

        $otherPatient = Patient::factory()->create(['admitting_doctor_id' => $referringDoctor->id]);
        Admission::factory()->create([
            'patient_id' => $otherPatient->id,
            'admission_date' => '2026-10-05 10:00:00',
        ]);

        $response = $this->actingAs($cashier, 'sanctum')->getJson(
            "/api/reports/doctor-revenue?doctor_id={$referringDoctor->id}&doctor_role=referring&from=2026-10-01&to=2026-10-31"
        );

        $response->assertOk()->assertJsonPath('summary.admissions_count', 1);
    }

    public function test_admissions_outside_the_date_range_are_excluded(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create(['admitting_doctor_id' => $doctor->id]);

        Admission::factory()->create(['patient_id' => $patient->id, 'admission_date' => '2026-09-30 23:00:00']);
        Admission::factory()->create(['patient_id' => $patient->id, 'admission_date' => '2026-11-01 00:30:00']);

        $response = $this->actingAs($cashier, 'sanctum')->getJson(
            "/api/reports/doctor-revenue?doctor_id={$doctor->id}&doctor_role=admitting&from=2026-10-01&to=2026-10-31"
        );

        $response->assertOk()->assertJsonPath('summary.admissions_count', 0);
    }

    public function test_doctor_id_and_role_are_required(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/doctor-revenue');

        $response->assertUnprocessable()->assertJsonValidationErrors(['doctor_id', 'doctor_role']);
    }

    public function test_doctor_cannot_view_doctor_revenue_report(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $linkedDoctor = Doctor::factory()->create();

        $response = $this->actingAs($doctor, 'sanctum')->getJson(
            "/api/reports/doctor-revenue?doctor_id={$linkedDoctor->id}&doctor_role=admitting"
        );

        $response->assertForbidden();
    }
}
