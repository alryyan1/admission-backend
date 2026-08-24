<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Patient;
use App\Models\Room;
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

    public function test_admitting_to_a_short_stay_bed_accepts_12_or_24_hours(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_short_stay' => true]);
        $bed = Bed::factory()->create(['room_id' => $room->id, 'status' => 'available']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => Patient::factory()->create()->id,
            'bed_id' => $bed->id,
            'admission_duration_hours' => 12,
        ]);

        $response->assertCreated()->assertJsonPath('admission_duration_hours', 12);
    }

    public function test_admitting_to_a_short_stay_bed_rejects_arbitrary_durations(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_short_stay' => true]);
        $bed = Bed::factory()->create(['room_id' => $room->id, 'status' => 'available']);

        foreach ([6, 18, 36, 48] as $hours) {
            $response = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
                'patient_id' => Patient::factory()->create()->id,
                'bed_id' => $bed->id,
                'admission_duration_hours' => $hours,
            ]);

            $response->assertUnprocessable();
        }
    }

    public function test_admitting_to_a_short_stay_bed_without_a_duration_is_rejected(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_short_stay' => true]);
        $bed = Bed::factory()->create(['room_id' => $room->id, 'status' => 'available']);

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
