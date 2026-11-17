<?php

namespace Tests\Feature;

use App\Models\GuardianRelationship;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianRelationshipControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_relationships_are_seeded_by_the_migration(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/guardian-relationships');

        $response->assertOk()->assertJsonCount(4);
        $this->assertDatabaseHas('guardian_relationships', ['name' => 'الأب']);
        $this->assertDatabaseHas('guardian_relationships', ['name' => 'الأم']);
        $this->assertDatabaseHas('guardian_relationships', ['name' => 'الزوجة']);
        $this->assertDatabaseHas('guardian_relationships', ['name' => 'الزوج']);
    }

    public function test_admin_can_create_a_guardian_relationship(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/guardian-relationships', [
            'name' => 'الأخ',
        ]);

        $response->assertCreated()->assertJsonPath('name', 'الأخ');
        $this->assertDatabaseHas('guardian_relationships', ['name' => 'الأخ']);
    }

    public function test_non_admin_cannot_create_a_guardian_relationship(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/guardian-relationships', [
            'name' => 'الأخ',
        ]);

        $response->assertForbidden();
    }

    public function test_creating_a_guardian_relationship_requires_a_unique_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/guardian-relationships', [
            'name' => 'الأب',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_rename_a_guardian_relationship(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $relationship = GuardianRelationship::factory()->create(['name' => 'قديم']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/guardian-relationships/{$relationship->id}", [
            'name' => 'جديد',
        ]);

        $response->assertOk()->assertJsonPath('name', 'جديد');
    }

    public function test_admin_can_delete_a_guardian_relationship_and_unlink_its_patients(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $relationship = GuardianRelationship::factory()->create();
        $patient = Patient::factory()->create(['guardian_relationship_id' => $relationship->id]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/guardian-relationships/{$relationship->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('guardian_relationships', ['id' => $relationship->id]);
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'guardian_relationship_id' => null]);
    }
}
