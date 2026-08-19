<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdmissionRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*/all-doctors*' => Http::response(['data' => []], 200)]);
    }

    public function test_nurse_cannot_admit_a_patient(): void
    {
        $nurse = User::factory()->role('nurse')->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $response = $this->actingAs($nurse, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
        ]);

        $response->assertForbidden();
    }

    public function test_admission_clerk_can_admit_a_patient(): void
    {
        $clerk = User::factory()->role('admission_clerk')->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
        ]);

        $response->assertCreated();
    }

    public function test_doctor_cannot_record_vital_signs(): void
    {
        $doctor = User::factory()->role('doctor')->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($doctor, 'sanctum')->postJson("/api/admissions/{$admission->id}/vitals", [
            'temperature' => 37.5,
            'pulse' => 80,
        ]);

        $response->assertForbidden();
    }

    public function test_nurse_can_record_vital_signs(): void
    {
        $nurse = User::factory()->role('nurse')->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($nurse, 'sanctum')->postJson("/api/admissions/{$admission->id}/vitals", [
            'temperature' => 37.5,
            'pulse' => 80,
        ]);

        $response->assertCreated();
    }

    public function test_nurse_cannot_record_a_deposit(): void
    {
        $nurse = User::factory()->role('nurse')->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($nurse, 'sanctum')->postJson("/api/admissions/{$admission->id}/deposits", [
            'amount' => 50000,
            'method' => 'cash',
        ]);

        $response->assertForbidden();
    }

    public function test_cashier_can_record_a_deposit(): void
    {
        $cashier = User::factory()->role('cashier')->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($cashier, 'sanctum')->postJson("/api/admissions/{$admission->id}/deposits", [
            'amount' => 50000,
            'method' => 'cash',
        ]);

        $response->assertCreated();
    }
}
