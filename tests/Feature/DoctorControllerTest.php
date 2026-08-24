<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_doctors(): void
    {
        $user = User::factory()->create();
        Doctor::factory()->count(2)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/doctors');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_authenticated_user_can_search_doctors_by_name(): void
    {
        $user = User::factory()->create();
        Doctor::factory()->create(['name' => 'د. أحمد سالم']);
        Doctor::factory()->create(['name' => 'د. منى خالد']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/doctors?search=أحمد');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'د. أحمد سالم');
    }

    public function test_admin_can_create_a_doctor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = TeamRole::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/doctors', [
            'name' => 'د. سارة علي',
            'specialist' => 'قلبية',
            'role_id' => $role->id,
        ]);

        $response->assertCreated()->assertJsonPath('name', 'د. سارة علي');
        $this->assertDatabaseHas('doctors', ['name' => 'د. سارة علي', 'specialist' => 'قلبية', 'role_id' => $role->id]);
    }

    public function test_creating_a_doctor_requires_a_valid_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/doctors', [
            'name' => 'د. سارة علي',
            'role_id' => 999999,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['role_id']);
    }

    public function test_non_admin_cannot_create_a_doctor(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);
        $role = TeamRole::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/doctors', [
            'name' => 'د. سارة علي',
            'role_id' => $role->id,
        ]);

        $response->assertForbidden();
    }

    public function test_listing_doctors_can_filter_by_role(): void
    {
        $user = User::factory()->create();
        $surgeon = TeamRole::factory()->create();
        $scrubNurse = TeamRole::factory()->create();
        Doctor::factory()->create(['name' => 'د. جراح', 'role_id' => $surgeon->id]);
        Doctor::factory()->create(['name' => 'د. ممرضة', 'role_id' => $scrubNurse->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/doctors?role_id={$scrubNurse->id}");

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'د. ممرضة');
    }

    public function test_admin_can_update_a_doctor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $doctor = Doctor::factory()->create(['name' => 'د. قديم']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/doctors/{$doctor->id}", [
            'name' => 'د. جديد',
        ]);

        $response->assertOk()->assertJsonPath('name', 'د. جديد');
    }

    public function test_admin_can_delete_an_unreferenced_doctor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/doctors/{$doctor->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('doctors', ['id' => $doctor->id]);
    }
}
