<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_totals_bed_charges_services_and_deposits(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['price_per_day' => 50000]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create([
            'bed_id' => $bed->id,
            'admission_date' => now()->subDays(2),
        ]);

        $admission->requestedServices()->create(['name' => 'أشعة', 'quantity' => 2, 'unit_price' => 10000]);
        $admission->deposits()->create(['amount' => 30000, 'paid_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admissions/{$admission->id}/invoice");

        $response->assertOk();
        $response->assertJsonPath('nights_stayed', 2);
        $response->assertJsonPath('bed_charges', 100000);
        $response->assertJsonPath('services_total', 20000);
        $response->assertJsonPath('total', 120000);
        $response->assertJsonPath('deposits_total', 30000);
        $response->assertJsonPath('balance_due', 90000);
    }

    public function test_short_stay_admission_is_billed_the_configured_12_hour_price(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_short_stay' => true, 'price_12_hours' => 80000, 'price_24_hours' => 120000]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create(['bed_id' => $bed->id, 'admission_duration_hours' => 12]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admissions/{$admission->id}/invoice");

        $response->assertOk();
        $response->assertJsonPath('billing_mode', 'short_stay');
        $response->assertJsonPath('bed_charges', 80000);
        $response->assertJsonPath('total', 80000);
    }

    public function test_short_stay_admission_is_billed_the_configured_24_hour_price(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_short_stay' => true, 'price_12_hours' => 80000, 'price_24_hours' => 120000]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create(['bed_id' => $bed->id, 'admission_duration_hours' => 24]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admissions/{$admission->id}/invoice");

        $response->assertOk();
        $response->assertJsonPath('bed_charges', 120000);
    }

    public function test_short_stay_admission_invoice_fails_clearly_when_room_pricing_is_not_configured(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_short_stay' => true, 'price_12_hours' => null, 'price_24_hours' => null]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create(['bed_id' => $bed->id, 'admission_duration_hours' => 12]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admissions/{$admission->id}/invoice");

        $response->assertUnprocessable();
    }

    public function test_normal_room_admission_ignores_short_stay_pricing(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create([
            'is_short_stay' => false,
            'price_per_day' => 50000,
            'price_12_hours' => 999999,
            'price_24_hours' => 999999,
        ]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create(['bed_id' => $bed->id, 'admission_date' => now()->subDays(2)]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admissions/{$admission->id}/invoice");

        $response->assertOk();
        $response->assertJsonPath('billing_mode', 'daily');
        $response->assertJsonPath('bed_charges', 100000);
    }
}
