<?php

namespace Tests\Feature;

use App\Models\Specialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialistControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_specialists(): void
    {
        $user = User::factory()->create();
        Specialist::factory()->count(2)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/specialists');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_admin_can_create_a_specialist(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/specialists', [
            'name' => 'قلبية',
        ]);

        $response->assertCreated()->assertJsonPath('name', 'قلبية');
        $this->assertDatabaseHas('specialists', ['name' => 'قلبية']);
    }

    public function test_non_admin_cannot_create_a_specialist(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/specialists', [
            'name' => 'قلبية',
        ]);

        $response->assertForbidden();
    }

    public function test_creating_a_specialist_requires_a_unique_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Specialist::factory()->create(['name' => 'قلبية']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/specialists', [
            'name' => 'قلبية',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_rename_a_specialist(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $specialist = Specialist::factory()->create(['name' => 'قديم']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/specialists/{$specialist->id}", [
            'name' => 'جديد',
        ]);

        $response->assertOk()->assertJsonPath('name', 'جديد');
    }

    public function test_admin_can_delete_an_unreferenced_specialist(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $specialist = Specialist::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/specialists/{$specialist->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('specialists', ['id' => $specialist->id]);
    }
}
