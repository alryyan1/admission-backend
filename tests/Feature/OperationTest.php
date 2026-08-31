<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Doctor;
use App\Models\Operation;
use App\Models\Procedure;
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
        $procedure = Procedure::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/admissions/{$admission->id}/operations", [
            'surgeon_id' => Doctor::factory()->create()->id,
            'procedure_id' => $procedure->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ]);

        $response->assertCreated();
        $this->assertMatchesRegularExpression('/^\d+$/', $response->json('operation_number'));
    }

    public function test_scheduling_an_operation_stores_its_price_and_adds_no_requested_service(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $procedure = Procedure::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/admissions/{$admission->id}/operations", [
            'surgeon_id' => Doctor::factory()->create()->id,
            'procedure_id' => $procedure->id,
            'price' => 75000,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ]);

        $response->assertCreated()->assertJsonPath('price', '75000.00');
        $this->assertDatabaseHas('operations', ['admission_id' => $admission->id, 'price' => 75000]);
        $this->assertDatabaseMissing('requested_services', ['admission_id' => $admission->id]);
    }

    public function test_updating_an_operation(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create();
        $newProcedure = Procedure::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}", ['procedure_id' => $newProcedure->id]);

        $response->assertOk();
        $this->assertDatabaseHas('operations', ['id' => $operation->id, 'procedure_id' => $newProcedure->id]);
    }

    public function test_validation_requires_surgeon_procedure_and_schedule(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/admissions/{$admission->id}/operations", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['surgeon_id', 'procedure_id']);
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

    public function test_global_listing_filters_by_date(): void
    {
        $user = User::factory()->create();
        Operation::factory()->create(['scheduled_at' => '2026-01-10 09:00:00']);
        Operation::factory()->create(['scheduled_at' => '2026-02-20 09:00:00']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/operations?date=2026-01-10');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
