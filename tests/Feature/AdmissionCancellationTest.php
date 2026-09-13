<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Patient;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admitting_a_patient_generates_an_admission_number_and_type(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('admission_type', 'inpatient');
        $this->assertMatchesRegularExpression('/^\d+$/', $response->json('admission_number'));
    }

    public function test_admission_number_increments_per_day_and_resets_the_next_day(): void
    {
        $user = User::factory()->create();
        $beds = Bed::factory()->count(3)->create(['status' => 'available']);

        $first = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => Patient::factory()->create()->id,
            'bed_id' => $beds[0]->id,
            'admission_date' => '2026-09-10 09:00:00',
        ]);
        $second = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => Patient::factory()->create()->id,
            'bed_id' => $beds[1]->id,
            'admission_date' => '2026-09-10 15:00:00',
        ]);
        $nextDay = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => Patient::factory()->create()->id,
            'bed_id' => $beds[2]->id,
            'admission_date' => '2026-09-11 08:00:00',
        ]);

        $first->assertJsonPath('admission_number', '1');
        $second->assertJsonPath('admission_number', '2');
        $nextDay->assertJsonPath('admission_number', '1');
    }

    public function test_admitting_to_a_short_stay_bed_sets_short_stay_admission_type(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_short_stay' => true]);
        $bed = Bed::factory()->create(['room_id' => $room->id, 'status' => 'available']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => Patient::factory()->create()->id,
            'bed_id' => $bed->id,
        ]);

        $response->assertCreated()->assertJsonPath('admission_type', 'short_stay');
    }

    public function test_cancelling_an_admission_frees_the_bed(): void
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
            ->patchJson("/api/admissions/{$admission['id']}/cancel", [
                'cancellation_reason' => 'تم بالخطأ',
            ]);

        $response->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertDatabaseHas('beds', ['id' => $bed->id, 'status' => 'available']);
    }

    public function test_cannot_cancel_an_already_discharged_admission(): void
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
            ->patchJson("/api/admissions/{$admission['id']}/cancel", []);

        $response->assertUnprocessable();
    }

    public function test_cannot_discharge_a_cancelled_admission(): void
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
            ->patchJson("/api/admissions/{$admission['id']}/cancel", []);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/admissions/{$admission['id']}/discharge", []);

        $response->assertUnprocessable();
    }
}
