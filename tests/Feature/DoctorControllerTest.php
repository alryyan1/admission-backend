<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DoctorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_all_doctors(): void
    {
        $user = User::factory()->create();
        Doctor::factory()->create(['name' => 'د. أحمد سالم']);
        Doctor::factory()->create(['name' => 'د. منى خالد']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/doctors');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_authenticated_user_can_search_doctors_by_name(): void
    {
        $user = User::factory()->create();
        Doctor::factory()->create(['name' => 'د. أحمد سالم']);
        Doctor::factory()->create(['name' => 'د. منى خالد']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/doctors?search=أحمد');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'د. أحمد سالم');
    }

    public function test_listing_doctors_auto_syncs_from_jawda_when_cache_is_empty(): void
    {
        $user = User::factory()->create();
        Http::fake([
            '*/all-doctors*' => Http::response([
                'data' => [
                    ['id' => 1, 'name' => 'Out Patient', 'specialist_name' => 'General'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/doctors?search=out patient');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Out Patient');
        $this->assertDatabaseHas('doctors', ['jawda_doctor_id' => 1, 'name' => 'Out Patient']);
    }

    public function test_listing_doctors_does_not_resync_when_cache_already_populated(): void
    {
        $user = User::factory()->create();
        Doctor::factory()->create(['name' => 'د. أحمد سالم']);
        Http::fake();

        $this->actingAs($user, 'sanctum')->getJson('/api/doctors')->assertOk();

        Http::assertNothingSent();
    }
}
