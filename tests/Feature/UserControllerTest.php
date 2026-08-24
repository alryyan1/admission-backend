<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(2)->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/users');

        $response->assertOk()->assertJsonCount(3);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/users');

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/users', [
            'name' => 'محمد علي',
            'username' => 'mali',
            'password' => 'secret123',
            'role' => 'nurse',
        ]);

        $response->assertCreated()->assertJsonPath('name', 'محمد علي');
        $this->assertDatabaseHas('users', ['username' => 'mali', 'role' => 'nurse']);
    }

    public function test_creating_a_user_requires_a_unique_username(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['username' => 'taken']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/users', [
            'name' => 'اسم',
            'username' => 'taken',
            'password' => 'secret123',
            'role' => 'nurse',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['username']);
    }

    public function test_admin_can_update_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['name' => 'قديم']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/users/{$target->id}", [
            'name' => 'جديد',
        ]);

        $response->assertOk()->assertJsonPath('name', 'جديد');
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/users/{$admin->id}", [
            'is_active' => false,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['is_active']);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/users/{$target->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/users/{$admin->id}");

        $response->assertUnprocessable()->assertJsonValidationErrors(['user']);
    }
}
