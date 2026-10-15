<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\PaymentMethod;
use App\Models\RequestedService;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestedServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_a_service_marks_it_paid_and_creates_a_linked_deposit(): void
    {
        $user = User::factory()->role('cashier')->create();
        $admission = Admission::factory()->create();
        $service = RequestedService::factory()->create([
            'admission_id' => $admission->id,
            'quantity' => 2,
            'unit_price' => 5000,
        ]);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(
            "/api/admissions/{$admission->id}/services/{$service->id}/pay",
            ['payment_method_id' => $paymentMethod->id],
        );

        $response->assertOk()
            ->assertJsonPath('is_paid', true)
            ->assertJsonPath('payment_method.id', $paymentMethod->id);
        $this->assertDatabaseHas('requested_services', [
            'id' => $service->id,
            'payment_method_id' => $paymentMethod->id,
        ]);
        $this->assertDatabaseHas('admission_deposits', [
            'admission_id' => $admission->id,
            'requested_service_id' => $service->id,
            'amount' => 10000,
            'payment_method_id' => $paymentMethod->id,
        ]);
    }

    public function test_paying_an_already_paid_service_is_rejected(): void
    {
        $user = User::factory()->role('cashier')->create();
        $admission = Admission::factory()->create();
        $service = RequestedService::factory()->create(['admission_id' => $admission->id]);
        $admission->deposits()->create(['requested_service_id' => $service->id, 'amount' => $service->total_price]);

        $response = $this->actingAs($user, 'sanctum')->postJson(
            "/api/admissions/{$admission->id}/services/{$service->id}/pay",
        );

        $response->assertUnprocessable();
    }

    public function test_cancelling_a_payment_deletes_its_deposit_and_clears_the_paid_flag(): void
    {
        $user = User::factory()->role('cashier')->create();
        $admission = Admission::factory()->create();
        $service = RequestedService::factory()->create(['admission_id' => $admission->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/services/{$service->id}/pay")
            ->assertOk();

        $response = $this->actingAs($user, 'sanctum')->deleteJson(
            "/api/admissions/{$admission->id}/services/{$service->id}/pay",
        );

        $response->assertOk()
            ->assertJsonPath('is_paid', false)
            ->assertJsonPath('payment_method_id', null);
        $this->assertDatabaseHas('requested_services', ['id' => $service->id, 'payment_method_id' => null]);
        $this->assertDatabaseMissing('admission_deposits', ['requested_service_id' => $service->id]);
    }

    public function test_updating_a_service_rejects_a_nonexistent_payment_method(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $service = RequestedService::factory()->create(['admission_id' => $admission->id]);

        $response = $this->actingAs($user, 'sanctum')->patchJson(
            "/api/admissions/{$admission->id}/services/{$service->id}",
            ['payment_method_id' => 999999],
        );

        $response->assertUnprocessable()->assertJsonValidationErrors(['payment_method_id']);
    }

    public function test_accommodation_fee_creates_a_requested_service_priced_by_nights_and_room_rate(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_short_stay' => false, 'price_per_day' => 50000]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create(['bed_id' => $bed->id, 'admission_date' => now()->subDays(2)]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/services/accommodation-fee");

        $response->assertCreated();
        $response->assertJsonPath('name', 'رسوم الإقامة');
        $response->assertJsonPath('quantity', 2);
        $response->assertJsonPath('unit_price', '50000.00');
        $this->assertDatabaseHas('services', ['name_ar' => 'رسوم الإقامة']);
        $this->assertDatabaseHas('requested_services', [
            'admission_id' => $admission->id,
            'name' => 'رسوم الإقامة',
            'quantity' => 2,
            'unit_price' => 50000,
        ]);
    }

    public function test_accommodation_fee_reuses_an_existing_matching_service(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->create(['name_ar' => 'رسوم الإقامة', 'price' => 1]);
        $room = Room::factory()->create(['is_short_stay' => false, 'price_per_day' => 75000]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create(['bed_id' => $bed->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/services/accommodation-fee")
            ->assertCreated();

        $this->assertDatabaseCount('services', 1);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'name_ar' => 'رسوم الإقامة']);
    }

    public function test_accommodation_fee_is_charged_for_a_room_with_a_daily_price_of_any_size(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['price_per_day' => 30000]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create(['bed_id' => $bed->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/services/accommodation-fee");

        $response->assertCreated()->assertJsonPath('unit_price', '30000.00');
        $this->assertDatabaseCount('requested_services', 1);
    }

    public function test_accommodation_fee_rejects_a_room_without_a_daily_price(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_short_stay' => false, 'price_per_day' => null]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create(['bed_id' => $bed->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/services/accommodation-fee");

        $response->assertUnprocessable();
        $this->assertDatabaseCount('requested_services', 0);
    }

    public function test_nurse_cannot_calculate_accommodation_fee_for_a_discharged_admission(): void
    {
        $nurse = User::factory()->role('nurse')->create();
        $room = Room::factory()->create(['is_short_stay' => false, 'price_per_day' => 50000]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create(['bed_id' => $bed->id, 'status' => 'discharged']);

        $response = $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/services/accommodation-fee");

        $response->assertUnprocessable();
    }
}
