<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Doctor;
use App\Models\Operation;
use App\Models\OperationSupply;
use App\Models\OperationTeamMember;
use App\Models\Procedure;
use App\Models\TeamRole;
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

    public function test_global_listing_filters_by_created_at_date_range(): void
    {
        $user = User::factory()->create();
        $inRange = Operation::factory()->create();
        $beforeRange = Operation::factory()->create();
        $afterRange = Operation::factory()->create();

        $inRange->forceFill(['created_at' => '2026-01-10 09:00:00'])->save();
        $beforeRange->forceFill(['created_at' => '2025-12-31 23:59:00'])->save();
        $afterRange->forceFill(['created_at' => '2026-02-01 00:00:00'])->save();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/operations?date_from=2026-01-01&date_to=2026-01-31');

        $response->assertOk();
        $this->assertSame([$inRange->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_global_listing_includes_price_and_net_totals_across_all_matching_pages(): void
    {
        $user = User::factory()->create();

        $operationOne = Operation::factory()->create(['price' => '10000.00']);
        OperationTeamMember::factory()->create(['operation_id' => $operationOne->id, 'entitlement_amount' => '2000.00']);

        $operationTwo = Operation::factory()->create(['price' => '5000.00']);
        OperationTeamMember::factory()->create(['operation_id' => $operationTwo->id, 'entitlement_amount' => '1000.00']);

        // Outside the filtered date range, so it must not affect the totals.
        Operation::factory()->create(['price' => '99999.00'])->forceFill(['created_at' => now()->subYear()])->save();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/operations?per_page=1&date_from='.now()->subDay()->toDateString().'&date_to='.now()->addDay()->toDateString());

        $response->assertOk();
        $this->assertEquals(15000.0, $response->json('price_total'));
        $this->assertEquals(12000.0, $response->json('net_total'));
    }

    public function test_doctor_cannot_schedule_an_operation_on_a_cancelled_admission(): void
    {
        $doctor = User::factory()->role('doctor')->create();
        $admission = Admission::factory()->create(['status' => 'cancelled']);

        $response = $this->actingAs($doctor, 'sanctum')->postJson("/api/admissions/{$admission->id}/operations", [
            'surgeon_id' => Doctor::factory()->create()->id,
            'procedure_id' => Procedure::factory()->create()->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('operations', ['admission_id' => $admission->id]);
    }

    public function test_doctor_cannot_update_an_operation_on_a_cancelled_admission(): void
    {
        $doctor = User::factory()->role('doctor')->create();
        $operation = Operation::factory()->create([
            'admission_id' => Admission::factory()->create(['status' => 'cancelled'])->id,
        ]);
        $originalProcedureId = $operation->procedure_id;

        $response = $this->actingAs($doctor, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}", ['procedure_id' => Procedure::factory()->create()->id]);

        $response->assertUnprocessable();
        $this->assertDatabaseHas('operations', ['id' => $operation->id, 'procedure_id' => $originalProcedureId]);
    }

    public function test_doctor_cannot_add_a_team_member_to_an_operation_on_a_cancelled_admission(): void
    {
        $doctor = User::factory()->role('doctor')->create();
        $operation = Operation::factory()->create([
            'admission_id' => Admission::factory()->create(['status' => 'cancelled'])->id,
        ]);

        $response = $this->actingAs($doctor, 'sanctum')
            ->postJson("/api/operations/{$operation->id}/team-members", [
                'name' => 'Team member',
                'role_id' => TeamRole::factory()->create()->id,
            ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('operation_team_members', 0);
    }

    public function test_doctor_cannot_remove_a_team_member_from_an_operation_on_a_cancelled_admission(): void
    {
        $doctor = User::factory()->role('doctor')->create();
        $operation = Operation::factory()->create([
            'admission_id' => Admission::factory()->create(['status' => 'cancelled'])->id,
        ]);
        $teamMember = OperationTeamMember::factory()->create(['operation_id' => $operation->id]);

        $response = $this->actingAs($doctor, 'sanctum')
            ->deleteJson("/api/operations/{$operation->id}/team-members/{$teamMember->id}");

        $response->assertUnprocessable();
        $this->assertDatabaseHas('operation_team_members', ['id' => $teamMember->id]);
    }

    public function test_nurse_cannot_add_or_remove_supplies_on_a_cancelled_admission(): void
    {
        $nurse = User::factory()->role('nurse')->create();
        $operation = Operation::factory()->create([
            'admission_id' => Admission::factory()->create(['status' => 'cancelled'])->id,
        ]);
        $supply = OperationSupply::factory()->create(['operation_id' => $operation->id]);

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/operations/{$operation->id}/supplies", ['name' => 'Gauze'])
            ->assertUnprocessable();

        $this->actingAs($nurse, 'sanctum')
            ->deleteJson("/api/operations/{$operation->id}/supplies/{$supply->id}")
            ->assertUnprocessable();

        $this->assertDatabaseCount('operation_supplies', 1);
        $this->assertDatabaseHas('operation_supplies', ['id' => $supply->id]);
    }

    public function test_admin_can_update_an_operation_on_a_cancelled_admission(): void
    {
        $admin = User::factory()->role('admin')->create();
        $operation = Operation::factory()->create([
            'admission_id' => Admission::factory()->create(['status' => 'cancelled'])->id,
        ]);
        $newProcedure = Procedure::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/operations/{$operation->id}", ['procedure_id' => $newProcedure->id]);

        $response->assertOk();
        $this->assertDatabaseHas('operations', ['id' => $operation->id, 'procedure_id' => $newProcedure->id]);
    }
}
