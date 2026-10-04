<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\RoomTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_four_default_room_types(): void
    {
        $this->seed(RoomTypeSeeder::class);

        $this->assertDatabaseHas('room_types', ['code' => 'normal', 'name' => 'عادية']);
        $this->assertDatabaseHas('room_types', ['code' => 'vip', 'name' => 'خاصة']);
        $this->assertDatabaseHas('room_types', ['code' => 'nursery', 'name' => 'حضانة']);
        $this->assertDatabaseHas('room_types', ['code' => 'ward', 'name' => 'عنبر']);
        $this->assertDatabaseCount('room_types', 4);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RoomTypeSeeder::class);
        $this->seed(RoomTypeSeeder::class);

        $this->assertDatabaseCount('room_types', 4);
    }

    public function test_room_types_can_be_listed_by_any_authenticated_user(): void
    {
        RoomType::factory()->create(['code' => 'nursery', 'name' => 'حضانة']);

        $response = $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/room-types');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.code', 'nursery');
    }

    public function test_admin_can_add_a_room_type_with_a_generated_code(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')
            ->postJson('/api/room-types', ['name' => 'غرفة عزل']);

        $response->assertCreated()->assertJsonPath('name', 'غرفة عزل');
        $this->assertStringStartsWith('type_', $response->json('code'));
        $this->assertDatabaseHas('room_types', ['name' => 'غرفة عزل']);
    }

    public function test_non_admin_cannot_add_a_room_type(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'nurse']), 'sanctum')
            ->postJson('/api/room-types', ['name' => 'غرفة عزل']);

        $response->assertForbidden();
        $this->assertDatabaseCount('room_types', 0);
    }

    public function test_room_type_name_is_required_and_unique(): void
    {
        RoomType::factory()->create(['name' => 'عنبر']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/room-types', ['name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->actingAs($admin, 'sanctum')->postJson('/api/room-types', ['name' => 'عنبر'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_renaming_keeps_the_code_so_rooms_stay_linked(): void
    {
        $roomType = RoomType::factory()->create(['code' => 'vip', 'name' => 'خاصة']);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/room-types/{$roomType->id}", ['name' => 'VIP', 'code' => 'changed']);

        $response->assertOk()->assertJsonPath('code', 'vip')->assertJsonPath('name', 'VIP');
        $this->assertDatabaseHas('room_types', ['id' => $roomType->id, 'code' => 'vip']);
    }

    public function test_room_type_in_use_by_a_room_cannot_be_deleted(): void
    {
        $roomType = RoomType::factory()->create(['code' => 'nursery']);
        Room::factory()->create(['room_type' => 'nursery']);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')
            ->deleteJson("/api/room-types/{$roomType->id}");

        $response->assertUnprocessable()->assertJsonValidationErrors('room_type');
        $this->assertDatabaseHas('room_types', ['id' => $roomType->id]);
    }

    public function test_unused_room_type_can_be_deleted(): void
    {
        $roomType = RoomType::factory()->create();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')
            ->deleteJson("/api/room-types/{$roomType->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('room_types', ['id' => $roomType->id]);
    }

    public function test_room_can_be_created_with_a_user_added_room_type(): void
    {
        RoomType::factory()->create(['code' => 'type_isolation']);
        $ward = Ward::factory()->create();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')
            ->postJson('/api/rooms', [
                'ward_id' => $ward->id,
                'room_number' => '7',
                'room_type' => 'type_isolation',
                'capacity' => 1,
            ]);

        $response->assertCreated()->assertJsonPath('room_type', 'type_isolation');
    }

    public function test_room_cannot_use_a_room_type_that_does_not_exist(): void
    {
        $ward = Ward::factory()->create();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')
            ->postJson('/api/rooms', [
                'ward_id' => $ward->id,
                'room_number' => '7',
                'room_type' => 'unknown',
                'capacity' => 1,
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('room_type');
    }
}
