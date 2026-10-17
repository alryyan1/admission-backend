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

    public function test_admin_can_restrict_the_pages_a_user_may_see(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/users/{$target->id}", [
            'allowed_pages' => ['/admissions', '/reports/daily-revenue'],
        ]);

        $response->assertOk()->assertJsonPath('allowed_pages', ['/admissions', '/reports/daily-revenue']);
        $this->assertSame(['/admissions', '/reports/daily-revenue'], $target->fresh()->allowed_pages);
    }

    public function test_admin_can_clear_page_restrictions_for_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['allowed_pages' => ['/admissions']]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/users/{$target->id}", [
            'allowed_pages' => null,
        ]);

        $response->assertOk()->assertJsonPath('allowed_pages', null);
        $this->assertNull($target->fresh()->allowed_pages);
    }

    public function test_admin_cannot_change_their_own_page_restrictions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/users/{$admin->id}", [
            'allowed_pages' => ['/admissions'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['allowed_pages']);
    }

    public function test_admin_can_grant_the_direct_deposit_permission_to_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->role('cashier')->create();

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/users/{$target->id}", [
            'permissions' => [User::PERMISSION_ADD_DIRECT_DEPOSIT],
        ]);

        $response->assertOk()->assertJsonPath('permissions', [User::PERMISSION_ADD_DIRECT_DEPOSIT]);
        $this->assertSame([User::PERMISSION_ADD_DIRECT_DEPOSIT], $target->fresh()->permissions);
    }

    public function test_users_have_no_permissions_by_default(): void
    {
        $user = User::factory()->role('cashier')->create();

        $this->assertNull($user->permissions);
        $this->assertFalse($user->hasPermission(User::PERMISSION_ADD_DIRECT_DEPOSIT));
    }

    public function test_an_unknown_permission_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->role('cashier')->create();

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/users/{$target->id}", [
            'permissions' => ['not_a_real_permission'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['permissions.0']);
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
