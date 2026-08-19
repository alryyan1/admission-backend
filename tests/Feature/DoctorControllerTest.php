<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DoctorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeJawdaDoctors(): void
    {
        Http::fake([
            '*/all-doctors*' => Http::response([
                'data' => [
                    ['id' => 1, 'name' => 'د. أحمد سالم', 'specialist_name' => 'باطنية'],
                    ['id' => 2, 'name' => 'د. منى خالد', 'specialist_name' => 'أطفال'],
                ],
            ], 200),
        ]);
    }

    public function test_authenticated_user_can_list_all_doctors(): void
    {
        $user = User::factory()->create();
        $this->fakeJawdaDoctors();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/doctors');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_authenticated_user_can_search_doctors_by_name(): void
    {
        $user = User::factory()->create();
        $this->fakeJawdaDoctors();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/doctors?search=أحمد');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'د. أحمد سالم');
    }

    public function test_listing_doctors_always_resolves_live_from_jawda(): void
    {
        $user = User::factory()->create();
        $this->fakeJawdaDoctors();

        $this->actingAs($user, 'sanctum')->getJson('/api/doctors')->assertOk()->assertJsonCount(2);

        Http::assertSentCount(1);

        $this->actingAs($user, 'sanctum')->getJson('/api/doctors')->assertOk()->assertJsonCount(2);

        Http::assertSentCount(2);
    }
}
