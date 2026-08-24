<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Doctor;
use App\Models\Operation;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduling_an_operation_generates_an_operation_number(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $room = Room::factory()->create(['room_type' => 'operation']);
        $procedure = Procedure::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/admissions/{$admission->id}/operations", [
            'surgeon_id' => Doctor::factory()->create()->id,
            'operation_room_id' => $room->id,
            'procedure_id' => $procedure->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', 'scheduled');
        $this->assertMatchesRegularExpression('/^OP-\d{2}-\d{6}$/', $response->json('operation_number'));
    }

    public function test_full_lifecycle_from_scheduled_to_started_to_completed(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create();

        $this->actingAs($user, 'sanctum')->patchJson("/api/operations/{$operation->id}/prepare", [
            'consent_obtained' => true,
            'fasting_confirmed' => true,
            'site_marked' => true,
            'preop_vitals_checked' => true,
        ])->assertOk();

        $startResponse = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}/start");
        $startResponse->assertOk()->assertJsonPath('status', 'in_progress');
        $this->assertNotNull($startResponse->json('started_at'));

        $completeResponse = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}/complete", ['notes' => 'تمت بنجاح']);
        $completeResponse->assertOk()->assertJsonPath('status', 'completed');
        $this->assertNotNull($completeResponse->json('ended_at'));
        $completeResponse->assertJsonPath('notes', 'تمت بنجاح');
    }

    public function test_cancelling_a_scheduled_operation(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}/cancel", ['cancellation_reason' => 'تأجيل من المريض']);

        $response->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertDatabaseHas('operations', ['id' => $operation->id, 'cancellation_reason' => 'تأجيل من المريض']);
    }

    public function test_cancelling_an_in_progress_operation(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create(['status' => 'in_progress', 'started_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}/cancel");

        $response->assertOk()->assertJsonPath('status', 'cancelled');
    }

    public function test_cannot_start_an_unprepared_operation(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}/start");

        $response->assertUnprocessable();
    }

    public function test_cannot_start_a_non_scheduled_operation(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}/start");

        $response->assertUnprocessable();
    }

    public function test_cannot_complete_a_non_in_progress_operation(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create(['status' => 'scheduled']);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}/complete");

        $response->assertUnprocessable();
    }

    public function test_cannot_update_an_operation_once_it_is_no_longer_scheduled(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create(['status' => 'in_progress', 'started_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}", ['procedure_id' => Procedure::factory()->create()->id]);

        $response->assertUnprocessable();
    }

    public function test_validation_requires_surgeon_procedure_and_schedule(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/admissions/{$admission->id}/operations", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['surgeon_id', 'procedure_id', 'scheduled_at']);
    }

    public function test_nurse_cannot_schedule_an_operation(): void
    {
        $nurse = User::factory()->role('nurse')->create();
        $admission = Admission::factory()->create();
        $procedure = Procedure::factory()->create();

        $response = $this->actingAs($nurse, 'sanctum')->postJson("/api/admissions/{$admission->id}/operations", [
            'surgeon_id' => Doctor::factory()->create()->id,
            'procedure_id' => $procedure->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ]);

        $response->assertForbidden();
    }

    public function test_doctor_can_schedule_an_operation(): void
    {
        $doctor = User::factory()->role('doctor')->create();
        $admission = Admission::factory()->create();
        $procedure = Procedure::factory()->create();

        $response = $this->actingAs($doctor, 'sanctum')->postJson("/api/admissions/{$admission->id}/operations", [
            'surgeon_id' => Doctor::factory()->create()->id,
            'procedure_id' => $procedure->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ]);

        $response->assertCreated();
    }

    public function test_nurse_cannot_cancel_an_operation(): void
    {
        $nurse = User::factory()->role('nurse')->create();
        $operation = Operation::factory()->create();

        $response = $this->actingAs($nurse, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}/cancel");

        $response->assertForbidden();
    }

    public function test_index_lists_operations_for_an_admission(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        Operation::factory()->create(['admission_id' => $admission->id]);
        Operation::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admissions/{$admission->id}/operations");

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_global_listing_filters_by_status(): void
    {
        $user = User::factory()->create();
        Operation::factory()->create(['status' => 'scheduled']);
        Operation::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/operations?status=completed');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
