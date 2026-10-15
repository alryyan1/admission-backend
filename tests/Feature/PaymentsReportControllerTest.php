<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_recorders_lists_users_by_name(): void
    {
        $user = User::factory()->create(['name' => 'Zaid']);
        User::factory()->create(['name' => 'Adel']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/reports/payments/recorders');

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonPath('0.name', 'Adel');
        $response->assertJsonPath('1.name', 'Zaid');
        $response->assertJsonStructure([['id', 'name']]);
        $this->assertArrayNotHasKey('password', $response->json(0));
    }

    public function test_payments_recorders_requires_authentication(): void
    {
        $this->getJson('/api/reports/payments/recorders')->assertUnauthorized();
    }
}
