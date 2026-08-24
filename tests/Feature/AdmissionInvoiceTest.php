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

    public function test_invoice_totals_services_and_deposits_only(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['price_per_day' => 50000]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create([
            'bed_id' => $bed->id,
            'admission_date' => now()->subDays(2),
        ]);

        $admission->requestedServices()->create(['name' => 'أشعة', 'quantity' => 2, 'unit_price' => 10000]);
        $admission->deposits()->create(['amount' => 15000, 'paid_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admissions/{$admission->id}/invoice");

        $response->assertOk();
        $response->assertJsonPath('services_total', 20000);
        $response->assertJsonPath('total', 20000);
        $response->assertJsonPath('deposits_total', 15000);
        $response->assertJsonPath('balance_due', 5000);
    }

    public function test_invoice_ignores_room_and_short_stay_pricing(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_short_stay' => true, 'price_12_hours' => null, 'price_24_hours' => null]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create(['bed_id' => $bed->id, 'admission_duration_hours' => 12]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admissions/{$admission->id}/invoice");

        $response->assertOk();
        $response->assertJsonPath('services_total', 0);
        $response->assertJsonPath('total', 0);
    }
}
