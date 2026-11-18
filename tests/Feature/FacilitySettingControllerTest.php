<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilitySettingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_form_toggle_defaults_to_hidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/settings/facility');

        $response->assertOk()->assertJsonPath('show_patient_relative_fields', false);
    }

    public function test_admin_can_enable_the_relative_patient_form_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/settings/facility', [
            'show_patient_relative_fields' => '1',
        ]);

        $response->assertOk()->assertJsonPath('show_patient_relative_fields', true);
        $this->assertDatabaseHas('facility_settings', [
            'show_patient_relative_fields' => true,
        ]);
    }

    public function test_insurance_fields_toggle_defaults_to_shown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/settings/facility');

        $response->assertOk()->assertJsonPath('show_insurance_fields', true);
    }

    public function test_admin_can_hide_the_insurance_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/settings/facility', [
            'show_insurance_fields' => '0',
        ]);

        $response->assertOk()->assertJsonPath('show_insurance_fields', false);
        $this->assertDatabaseHas('facility_settings', [
            'show_insurance_fields' => false,
        ]);
    }

    public function test_non_admin_cannot_view_facility_settings(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/settings/facility');

        $response->assertForbidden();
    }
}
