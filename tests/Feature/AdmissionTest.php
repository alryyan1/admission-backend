<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admitting_a_patient_marks_the_bed_occupied(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
            'diagnosis' => 'حمى',
        ]);

        $response->assertCreated()->assertJsonPath('status', 'admitted');
        $this->assertDatabaseHas('beds', ['id' => $bed->id, 'status' => 'occupied']);
    }

    public function test_cannot_admit_a_patient_to_an_already_occupied_bed(): void
    {
        $user = User::factory()->create();
        $bed = Bed::factory()->create(['status' => 'occupied']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => Patient::factory()->create()->id,
            'bed_id' => $bed->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_discharging_a_patient_frees_the_bed(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $admission = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admissions', [
                'patient_id' => $patient->id,
                'bed_id' => $bed->id,
            ])->json();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/admissions/{$admission['id']}/discharge", [
                'discharge_summary' => 'تحسنت الحالة',
            ]);

        $response->assertOk()->assertJsonPath('status', 'discharged');
        $this->assertDatabaseHas('beds', ['id' => $bed->id, 'status' => 'available']);
    }

    public function test_cannot_discharge_an_already_discharged_admission(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $admission = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admissions', [
                'patient_id' => $patient->id,
                'bed_id' => $bed->id,
            ])->json();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/admissions/{$admission['id']}/discharge", []);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/admissions/{$admission['id']}/discharge", []);

        $response->assertUnprocessable();
    }

    public function test_cannot_discharge_with_a_date_earlier_than_admission_date(): void
    {
        $clerk = User::factory()->role('admission_clerk')->create();
        $doctor = User::factory()->role('doctor')->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $admission = $this->actingAs($clerk, 'sanctum')
            ->postJson('/api/admissions', [
                'patient_id' => $patient->id,
                'bed_id' => $bed->id,
                'admission_date' => now()->toDateTimeString(),
            ])->json();

        $response = $this->actingAs($doctor, 'sanctum')
            ->patchJson("/api/admissions/{$admission['id']}/discharge", [
                'discharge_date' => now()->subDay()->toDateTimeString(),
            ]);

        $response->assertUnprocessable();
    }

    public function test_non_admin_cannot_add_vital_signs_to_a_discharged_admission(): void
    {
        $admin = User::factory()->create();
        $nurse = User::factory()->role('nurse')->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $admission = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admissions', [
                'patient_id' => $patient->id,
                'bed_id' => $bed->id,
            ])->json();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admissions/{$admission['id']}/discharge", []);

        $response = $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/admissions/{$admission['id']}/vitals", [
                'temperature' => 37.5,
                'pulse' => 80,
            ]);

        $response->assertUnprocessable();
    }

    public function test_admission_clerk_can_update_admitting_and_referring_doctors(): void
    {
        $clerk = User::factory()->role('admission_clerk')->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);
        $admittingDoctor = Doctor::factory()->create();
        $referringDoctor = Doctor::factory()->create();

        $admission = $this->actingAs($clerk, 'sanctum')
            ->postJson('/api/admissions', [
                'patient_id' => $patient->id,
                'bed_id' => $bed->id,
            ])->json();

        $response = $this->actingAs($clerk, 'sanctum')
            ->patchJson("/api/admissions/{$admission['id']}", [
                'admitting_doctor_id' => $admittingDoctor->id,
                'referred_by_doctor_id' => $referringDoctor->id,
                'diagnosis' => 'التهاب رئوي',
            ]);

        $response->assertOk()
            ->assertJsonPath('admitting_doctor.id', $admittingDoctor->id)
            ->assertJsonPath('referred_by_doctor.id', $referringDoctor->id)
            ->assertJsonPath('diagnosis', 'التهاب رئوي');
    }

    public function test_non_admin_cannot_update_a_discharged_admission(): void
    {
        $admin = User::factory()->create();
        $clerk = User::factory()->role('admission_clerk')->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $admission = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admissions', [
                'patient_id' => $patient->id,
                'bed_id' => $bed->id,
            ])->json();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admissions/{$admission['id']}/discharge", []);

        $response = $this->actingAs($clerk, 'sanctum')
            ->patchJson("/api/admissions/{$admission['id']}", [
                'diagnosis' => 'تشخيص جديد',
            ]);

        $response->assertUnprocessable();
    }

    public function test_admissions_index_reports_outstanding_balance_due(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $admission = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admissions', [
                'patient_id' => $patient->id,
                'bed_id' => $bed->id,
            ])->json();

        Admission::find($admission['id'])->requestedServices()->create([
            'name' => 'أشعة',
            'quantity' => 1,
            'unit_price' => 10000,
        ]);
        Admission::find($admission['id'])->deposits()->create(['amount' => 4000, 'paid_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/admissions');

        $response->assertOk()->assertJsonFragment([
            'id' => $admission['id'],
            'total_charges' => 10000,
            'paid_total' => 4000,
            'balance_due' => 6000,
        ]);
    }

    public function test_admissions_index_filters_by_admission_id(): void
    {
        $user = User::factory()->create();
        $matching = Admission::factory()->create();
        Admission::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/admissions?admission_id={$matching->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$matching->id], $ids);
    }

    public function test_admin_can_still_add_vital_signs_to_a_discharged_admission(): void
    {
        $admin = User::factory()->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $admission = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admissions', [
                'patient_id' => $patient->id,
                'bed_id' => $bed->id,
            ])->json();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admissions/{$admission['id']}/discharge", []);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admissions/{$admission['id']}/vitals", [
                'temperature' => 37.5,
                'pulse' => 80,
            ]);

        $response->assertCreated();
    }
}
