<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountantControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_entitlement_can_rename_a_member_without_a_doctor(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create();
        $member = $operation->teamMembers()->create(['name' => 'جراح']);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/accountant/team-members/{$member->id}/entitlement", ['name' => 'د. أحمد سالم']);

        $response->assertOk()->assertJsonPath('name', 'د. أحمد سالم');
        $this->assertDatabaseHas('operation_team_members', ['id' => $member->id, 'name' => 'د. أحمد سالم']);
    }

    public function test_updating_entitlement_rejects_clearing_the_name_of_a_member_without_a_doctor(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create();
        $member = $operation->teamMembers()->create(['name' => 'جراح']);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/accountant/team-members/{$member->id}/entitlement", ['name' => null]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_updating_entitlement_allows_clearing_the_name_when_a_doctor_is_linked(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create();
        $member = $operation->teamMembers()->create(['doctor_id' => Doctor::factory()->create()->id, 'name' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/accountant/team-members/{$member->id}/entitlement", ['entitlement_amount' => 100]);

        $response->assertOk();
        $this->assertDatabaseHas('operation_team_members', ['id' => $member->id, 'entitlement_amount' => 100]);
    }

    public function test_updating_entitlement_can_assign_a_doctor_to_a_member_without_one(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create();
        $member = $operation->teamMembers()->create(['name' => 'جراح']);
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/accountant/team-members/{$member->id}/entitlement", ['doctor_id' => $doctor->id]);

        $response->assertOk()->assertJsonPath('doctor_id', $doctor->id);
        $this->assertDatabaseHas('operation_team_members', ['id' => $member->id, 'doctor_id' => $doctor->id]);
    }

    public function test_updating_entitlement_rejects_clearing_doctor_when_name_is_also_absent(): void
    {
        $user = User::factory()->create();
        $operation = Operation::factory()->create();
        $member = $operation->teamMembers()->create(['doctor_id' => Doctor::factory()->create()->id, 'name' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/accountant/team-members/{$member->id}/entitlement", ['doctor_id' => null]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }
}
