<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppRecipient;
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

    public function test_admin_can_list_add_and_remove_whatsapp_recipients(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/settings/whatsapp/recipients', ['phone' => '01012345678', 'label' => 'الإدارة'])
            ->assertCreated()
            ->assertJsonPath('phone', '01012345678');

        $recipient = WhatsAppRecipient::sole();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/settings/whatsapp/recipients')
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/settings/whatsapp/recipients/{$recipient->id}")
            ->assertNoContent();

        $this->assertDatabaseCount('whats_app_recipients', 0);
    }

    public function test_adding_a_duplicate_whatsapp_recipient_phone_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        WhatsAppRecipient::factory()->create(['phone' => '01012345678']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/settings/whatsapp/recipients', ['phone' => '01012345678'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_non_admin_cannot_manage_whatsapp_recipients(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);

        $this->actingAs($user, 'sanctum')->getJson('/api/settings/whatsapp/recipients')->assertForbidden();
    }
}
