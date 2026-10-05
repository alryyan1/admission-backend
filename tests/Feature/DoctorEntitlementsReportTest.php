<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Operation;
use App\Models\OperationTeamMember;
use App\Models\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorEntitlementsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_entitlements_are_grouped_per_doctor_with_paid_and_unpaid_totals(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $surgeon = Doctor::factory()->create(['name' => 'د. أحمد']);
        $anesthetist = Doctor::factory()->create(['name' => 'د. سارة']);
        $role = TeamRole::factory()->create(['name' => 'جراح']);

        $firstOperation = Operation::factory()->create(['scheduled_at' => '2026-10-03 09:00:00']);
        $secondOperation = Operation::factory()->create(['scheduled_at' => '2026-10-20 10:00:00']);

        OperationTeamMember::factory()->create([
            'operation_id' => $firstOperation->id,
            'doctor_id' => $surgeon->id,
            'role_id' => $role->id,
            'entitlement_amount' => 1000,
            'entitlement_paid_at' => '2026-10-04',
        ]);
        OperationTeamMember::factory()->create([
            'operation_id' => $secondOperation->id,
            'doctor_id' => $surgeon->id,
            'role_id' => $role->id,
            'entitlement_amount' => 400,
        ]);
        OperationTeamMember::factory()->create([
            'operation_id' => $secondOperation->id,
            'doctor_id' => $anesthetist->id,
            'role_id' => $role->id,
            'entitlement_amount' => 250,
            'entitlement_paid_at' => '2026-10-21',
        ]);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/doctor-entitlements?from=2026-10-01&to=2026-10-31');

        $response->assertOk()
            ->assertJsonPath('range.from', '2026-10-01')
            ->assertJsonPath('range.to', '2026-10-31')
            ->assertJsonPath('summary.operations_count', 2)
            ->assertJsonPath('summary.total', 1650)
            ->assertJsonPath('summary.paid', 1250)
            ->assertJsonPath('summary.unpaid', 400)
            ->assertJsonCount(2, 'doctors')
            ->assertJsonPath('doctors.0.name', 'د. أحمد')
            ->assertJsonPath('doctors.0.operations_count', 2)
            ->assertJsonPath('doctors.0.total', 1400)
            ->assertJsonPath('doctors.0.paid', 1000)
            ->assertJsonPath('doctors.0.unpaid', 400)
            ->assertJsonPath('doctors.0.role', 'جراح')
            ->assertJsonPath('doctors.1.name', 'د. سارة')
            ->assertJsonPath('doctors.1.total', 250)
            ->assertJsonCount(3, 'entitlements');
    }

    public function test_entitlements_outside_the_date_range_are_excluded(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $doctor = Doctor::factory()->create();

        $earlier = Operation::factory()->create(['scheduled_at' => '2026-09-30 23:00:00']);
        $later = Operation::factory()->create(['scheduled_at' => '2026-11-01 00:30:00']);

        OperationTeamMember::factory()->create(['operation_id' => $earlier->id, 'doctor_id' => $doctor->id, 'entitlement_amount' => 700]);
        OperationTeamMember::factory()->create(['operation_id' => $later->id, 'doctor_id' => $doctor->id, 'entitlement_amount' => 900]);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/doctor-entitlements?from=2026-10-01&to=2026-10-31');

        $response->assertOk()
            ->assertJsonCount(0, 'doctors')
            ->assertJsonCount(0, 'entitlements')
            ->assertJsonPath('summary.total', 0);
    }

    public function test_team_members_without_an_entitlement_amount_are_excluded(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $operation = Operation::factory()->create(['scheduled_at' => '2026-10-10 10:00:00']);

        OperationTeamMember::factory()->create([
            'operation_id' => $operation->id,
            'doctor_id' => Doctor::factory(),
            'entitlement_amount' => null,
        ]);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/doctor-entitlements?from=2026-10-01&to=2026-10-31');

        $response->assertOk()
            ->assertJsonCount(0, 'doctors')
            ->assertJsonCount(0, 'entitlements');
    }

    public function test_members_without_a_linked_doctor_are_grouped_by_their_free_text_name(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $operation = Operation::factory()->create(['scheduled_at' => '2026-10-10 10:00:00']);

        OperationTeamMember::factory()->create([
            'operation_id' => $operation->id,
            'doctor_id' => null,
            'name' => 'ممرض خارجي',
            'entitlement_amount' => 150,
        ]);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/doctor-entitlements?from=2026-10-01&to=2026-10-31');

        $response->assertOk()
            ->assertJsonPath('doctors.0.name', 'ممرض خارجي')
            ->assertJsonPath('doctors.0.total', 150)
            ->assertJsonPath('entitlements.0.doctor_name', 'ممرض خارجي');
    }

    public function test_range_defaults_to_the_current_month(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/doctor-entitlements');

        $response->assertOk()
            ->assertJsonPath('range.from', now()->startOfMonth()->toDateString())
            ->assertJsonPath('range.to', now()->endOfMonth()->toDateString());
    }

    public function test_range_rejects_an_end_date_before_the_start_date(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/doctor-entitlements?from=2026-10-31&to=2026-10-01');

        $response->assertUnprocessable()->assertJsonValidationErrors(['to']);
    }

    public function test_doctor_cannot_view_doctor_entitlements(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        $response = $this->actingAs($doctor, 'sanctum')->getJson('/api/reports/doctor-entitlements');

        $response->assertForbidden();
    }
}
