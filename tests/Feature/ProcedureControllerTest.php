<?php

namespace Tests\Feature;

use App\Models\Procedure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcedureControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_procedure_with_a_base_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/procedures', [
            'name_ar' => 'استئصال الزائدة',
            'base_price' => 1500.50,
        ]);

        $response->assertCreated()->assertJsonPath('base_price', '1500.50');
        $this->assertDatabaseHas('procedures', ['name_ar' => 'استئصال الزائدة', 'base_price' => 1500.50]);
    }

    public function test_base_price_is_optional_when_creating_a_procedure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/procedures', [
            'name_ar' => 'استئصال الزائدة',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('procedures', ['name_ar' => 'استئصال الزائدة', 'base_price' => null]);
    }

    public function test_base_price_must_not_be_negative(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/procedures', [
            'name_ar' => 'استئصال الزائدة',
            'base_price' => -10,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['base_price']);
    }

    public function test_admin_can_update_a_procedures_base_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $procedure = Procedure::factory()->create(['base_price' => 1000]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/procedures/{$procedure->id}", [
            'base_price' => 2500,
        ]);

        $response->assertOk()->assertJsonPath('base_price', '2500.00');
        $this->assertDatabaseHas('procedures', ['id' => $procedure->id, 'base_price' => 2500]);
    }

    public function test_non_admin_cannot_create_a_procedure(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/procedures', [
            'name_ar' => 'استئصال الزائدة',
            'base_price' => 1500,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('procedures', 0);
    }

    public function test_authenticated_user_can_list_procedures_with_base_price(): void
    {
        $user = User::factory()->create();
        Procedure::factory()->create(['name_ar' => 'استئصال الزائدة', 'base_price' => 1500]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/procedures');

        $response->assertOk()->assertJsonPath('0.base_price', '1500.00');
    }
}
