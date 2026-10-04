<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloorWardRoomBedTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_floor(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/floors', [
            'name' => 'الطابق الأول',
            'description' => 'قسم التنويم',
        ]);

        $response->assertCreated()->assertJsonPath('name', 'الطابق الأول');
        $this->assertDatabaseHas('floors', ['name' => 'الطابق الأول']);
    }

    public function test_authenticated_user_can_create_ward_under_floor(): void
    {
        $user = User::factory()->create();
        $floor = Floor::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/wards', [
            'floor_id' => $floor->id,
            'name' => 'عنبر 1 حريمات',
            'description' => 'قسم النساء',
            'gender' => 'female',
        ]);

        $response->assertCreated()->assertJsonPath('name', 'عنبر 1 حريمات');
        $this->assertDatabaseHas('wards', ['floor_id' => $floor->id, 'name' => 'عنبر 1 حريمات', 'gender' => 'female']);
    }

    public function test_creating_room_requires_unique_room_number_within_ward(): void
    {
        $user = User::factory()->create();
        $ward = Ward::factory()->create();
        RoomType::factory()->create(['code' => 'normal']);
        Room::factory()->create(['ward_id' => $ward->id, 'room_number' => '1']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/rooms', [
            'ward_id' => $ward->id,
            'room_number' => '1',
            'room_type' => 'normal',
            'capacity' => 5,
            'price_per_day' => 50000,
        ]);

        $response->assertUnprocessable();
    }

    public function test_creating_bed_makes_it_available_by_default(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/beds', [
            'room_id' => $room->id,
            'bed_number' => '1',
        ]);

        $response->assertCreated()->assertJsonPath('status', 'available');
    }

    public function test_room_ignores_removed_short_stay_fields(): void
    {
        $user = User::factory()->create();
        $ward = Ward::factory()->create();
        RoomType::factory()->create(['code' => 'normal']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/rooms', [
            'ward_id' => $ward->id,
            'room_number' => '1',
            'room_type' => 'normal',
            'capacity' => 11,
            'price_per_day' => 80000,
            'is_short_stay' => true,
            'price_12_hours' => 80000,
            'price_24_hours' => 120000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('price_per_day', '80000.00')
            ->assertJsonMissingPath('is_short_stay')
            ->assertJsonMissingPath('price_12_hours')
            ->assertJsonMissingPath('price_24_hours');
    }

    public function test_cannot_delete_a_bed_with_admission_history(): void
    {
        $user = User::factory()->create();
        $bed = Bed::factory()->create();
        Admission::factory()->create(['bed_id' => $bed->id]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/beds/{$bed->id}");

        $response->assertUnprocessable();
        $this->assertDatabaseHas('beds', ['id' => $bed->id]);
    }

    public function test_cannot_change_status_of_a_bed_with_an_active_admission(): void
    {
        $user = User::factory()->create();
        $bed = Bed::factory()->create(['status' => 'occupied']);
        Admission::factory()->create(['bed_id' => $bed->id, 'status' => 'admitted']);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/beds/{$bed->id}", [
            'status' => 'maintenance',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseHas('beds', ['id' => $bed->id, 'status' => 'occupied']);
    }

    public function test_creating_room_with_auto_create_beds_makes_numbered_beds_equal_to_capacity(): void
    {
        $user = User::factory()->create();
        $ward = Ward::factory()->create();
        RoomType::factory()->create(['code' => 'normal']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/rooms', [
            'ward_id' => $ward->id,
            'room_number' => '101',
            'room_type' => 'normal',
            'capacity' => 3,
            'auto_create_beds' => true,
        ]);

        $response->assertCreated()->assertJsonPath('capacity', 3);
        $room = Room::query()->findOrFail($response->json('id'));
        $this->assertSame(['1', '2', '3'], $room->beds->pluck('bed_number')->all());
        $this->assertTrue($room->beds->every(fn (Bed $bed): bool => $bed->unit_type === 'bed' && $bed->status === 'available'));
    }

    public function test_creating_room_without_auto_create_beds_creates_no_beds(): void
    {
        $user = User::factory()->create();
        $ward = Ward::factory()->create();
        RoomType::factory()->create(['code' => 'normal']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/rooms', [
            'ward_id' => $ward->id,
            'room_number' => '102',
            'room_type' => 'normal',
            'capacity' => 3,
            'auto_create_beds' => false,
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('beds', 0);
    }

    public function test_auto_create_beds_with_zero_capacity_creates_no_beds(): void
    {
        $user = User::factory()->create();
        $ward = Ward::factory()->create();
        RoomType::factory()->create(['code' => 'normal']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/rooms', [
            'ward_id' => $ward->id,
            'room_number' => '103',
            'room_type' => 'normal',
            'capacity' => 0,
            'auto_create_beds' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('beds', 0);
    }

    public function test_auto_create_beds_must_be_boolean(): void
    {
        $user = User::factory()->create();
        $ward = Ward::factory()->create();
        RoomType::factory()->create(['code' => 'normal']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/rooms', [
            'ward_id' => $ward->id,
            'room_number' => '104',
            'room_type' => 'normal',
            'capacity' => 2,
            'auto_create_beds' => 'maybe',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('auto_create_beds');
        $this->assertDatabaseCount('rooms', 0);
    }
}
