<?php

namespace Tests\Feature;

use App\Models\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_team_roles(): void
    {
        $user = User::factory()->create();
        $countBefore = TeamRole::count();
        TeamRole::factory()->count(2)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/team-roles');

        $response->assertOk()->assertJsonCount($countBefore + 2);
    }

    public function test_team_roles_are_seeded_with_a_protected_surgeon_role(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/team-roles');

        $response->assertOk()->assertJsonFragment(['slug' => 'surgeon', 'name' => 'جراح', 'is_protected' => true]);
    }

    public function test_admin_can_create_a_team_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/team-roles', [
            'name' => 'استشاري',
        ]);

        $response->assertCreated()->assertJsonPath('name', 'استشاري');
        $this->assertDatabaseHas('team_roles', ['name' => 'استشاري', 'is_protected' => false]);
    }

    public function test_non_admin_cannot_create_a_team_role(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/team-roles', [
            'name' => 'استشاري',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_rename_a_team_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = TeamRole::factory()->create(['name' => 'قديم']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/team-roles/{$role->id}", [
            'name' => 'جديد',
        ]);

        $response->assertOk()->assertJsonPath('name', 'جديد');
    }

    public function test_admin_can_delete_an_unreferenced_team_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = TeamRole::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/team-roles/{$role->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('team_roles', ['id' => $role->id]);
    }

    public function test_admin_cannot_delete_a_protected_team_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = TeamRole::where('slug', 'surgeon')->firstOrFail();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/team-roles/{$role->id}");

        $response->assertUnprocessable()->assertJsonValidationErrors(['team_role']);
        $this->assertDatabaseHas('team_roles', ['id' => $role->id]);
    }
}
