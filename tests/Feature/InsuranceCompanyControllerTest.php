<?php

namespace Tests\Feature;

use App\Models\InsuranceCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsuranceCompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_insurance_companies_ordered_by_name(): void
    {
        $user = User::factory()->create();
        InsuranceCompany::factory()->create(['name' => 'بوبا']);
        InsuranceCompany::factory()->create(['name' => 'التعاونية']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/insurance-companies');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'التعاونية')
            ->assertJsonPath('1.name', 'بوبا');
    }

    public function test_admin_can_create_an_insurance_company_with_phone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/insurance-companies', [
            'name' => 'الأهلية',
            'phone' => '0501234567',
        ]);

        $response->assertCreated()->assertJsonPath('name', 'الأهلية');
        $this->assertDatabaseHas('insurance_companies', ['name' => 'الأهلية', 'phone' => '0501234567']);
    }

    public function test_phone_is_optional_when_creating_an_insurance_company(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/insurance-companies', [
            'name' => 'الأهلية',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('insurance_companies', ['name' => 'الأهلية', 'phone' => null]);
    }

    public function test_non_admin_cannot_create_an_insurance_company(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/insurance-companies', [
            'name' => 'الأهلية',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('insurance_companies', 0);
    }

    public function test_creating_an_insurance_company_requires_a_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/insurance-companies', [
            'phone' => '0501234567',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_creating_an_insurance_company_requires_a_unique_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        InsuranceCompany::factory()->create(['name' => 'التعاونية']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/insurance-companies', [
            'name' => 'التعاونية',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_update_an_insurance_company_phone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $insuranceCompany = InsuranceCompany::factory()->create(['phone' => '0500000000']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/insurance-companies/{$insuranceCompany->id}", [
            'phone' => '0509999999',
        ]);

        $response->assertOk()->assertJsonPath('phone', '0509999999');
        $this->assertDatabaseHas('insurance_companies', ['id' => $insuranceCompany->id, 'phone' => '0509999999']);
    }

    public function test_admin_can_rename_an_insurance_company_to_its_own_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $insuranceCompany = InsuranceCompany::factory()->create(['name' => 'بوبا']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/insurance-companies/{$insuranceCompany->id}", [
            'name' => 'بوبا',
        ]);

        $response->assertOk()->assertJsonPath('name', 'بوبا');
    }

    public function test_updating_an_insurance_company_requires_a_unique_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        InsuranceCompany::factory()->create(['name' => 'التعاونية']);
        $insuranceCompany = InsuranceCompany::factory()->create(['name' => 'بوبا']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/insurance-companies/{$insuranceCompany->id}", [
            'name' => 'التعاونية',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_non_admin_cannot_update_an_insurance_company(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);
        $insuranceCompany = InsuranceCompany::factory()->create(['name' => 'بوبا']);

        $response = $this->actingAs($user, 'sanctum')->patchJson("/api/insurance-companies/{$insuranceCompany->id}", [
            'name' => 'جديد',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('insurance_companies', ['id' => $insuranceCompany->id, 'name' => 'بوبا']);
    }

    public function test_admin_can_delete_an_insurance_company(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $insuranceCompany = InsuranceCompany::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/insurance-companies/{$insuranceCompany->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('insurance_companies', ['id' => $insuranceCompany->id]);
    }

    public function test_non_admin_cannot_delete_an_insurance_company(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);
        $insuranceCompany = InsuranceCompany::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/insurance-companies/{$insuranceCompany->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('insurance_companies', ['id' => $insuranceCompany->id]);
    }
}
