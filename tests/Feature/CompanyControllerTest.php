<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_companies(): void
    {
        $user = User::factory()->create();
        Company::factory()->count(2)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/companies');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_admin_can_create_a_company(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/companies', [
            'name' => 'شركة سابك',
        ]);

        $response->assertCreated()->assertJsonPath('name', 'شركة سابك');
        $this->assertDatabaseHas('companies', ['name' => 'شركة سابك']);
    }

    public function test_non_admin_cannot_create_a_company(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/companies', [
            'name' => 'شركة سابك',
        ]);

        $response->assertForbidden();
    }

    public function test_creating_a_company_requires_a_unique_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Company::factory()->create(['name' => 'شركة سابك']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/companies', [
            'name' => 'شركة سابك',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_rename_a_company(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $company = Company::factory()->create(['name' => 'قديم']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/companies/{$company->id}", [
            'name' => 'جديد',
        ]);

        $response->assertOk()->assertJsonPath('name', 'جديد');
    }

    public function test_admin_can_delete_a_company_and_unlink_its_patients(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $company = Company::factory()->create();
        $patient = Patient::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/companies/{$company->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'company_id' => null]);
    }
}
