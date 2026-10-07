<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_whatsapp_settings_with_display_number(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'display_phone_number' => '+20 10 1234 5678',
                'verified_name' => 'Jawda Hospital',
                'quality_rating' => 'GREEN',
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/settings/whatsapp');

        $response->assertOk()->assertJson([
            'configured' => true,
            'display_phone_number' => '+20 10 1234 5678',
            'verified_name' => 'Jawda Hospital',
            'quality_rating' => 'GREEN',
            'error' => null,
        ]);
    }

    public function test_whatsapp_settings_report_unconfigured_state(): void
    {
        config(['services.whatsapp.phone_number_id' => null, 'services.whatsapp.access_token' => null]);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/settings/whatsapp');

        $response->assertOk()->assertJson(['configured' => false, 'display_phone_number' => null]);
    }

    public function test_admin_can_send_a_hello_world_test_message(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.default_country_code' => '20',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/settings/whatsapp/test', [
            'phone' => '01012345678',
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            return $request['to'] === '201012345678'
                && $request['template']['name'] === 'hello_world'
                && $request['template']['language']['code'] === 'en_US';
        });
    }

    public function test_non_admin_cannot_view_whatsapp_settings(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/settings/whatsapp');

        $response->assertForbidden();
    }
}
