<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Operation;
use App\Models\Procedure;
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

    public function test_invoice_includes_priced_operations_as_line_items(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();

        $admission->requestedServices()->create(['name' => 'أشعة', 'quantity' => 1, 'unit_price' => 10000]);
        $procedure = Procedure::factory()->create(['name_ar' => 'استئصال الزائدة']);
        Operation::factory()->for($admission)->create(['procedure_id' => $procedure->id, 'price' => 90000]);
        Operation::factory()->for($admission)->create(['price' => null]);
        $admission->deposits()->create(['amount' => 20000, 'paid_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admissions/{$admission->id}/invoice");

        $response->assertOk();
        $response->assertJsonPath('services_total', 10000);
        $response->assertJsonPath('operations_total', 90000);
        $response->assertJsonPath('total', 100000);
        $response->assertJsonPath('balance_due', 80000);
        $response->assertJsonCount(2, 'requested_services');
        $response->assertJsonFragment(['name' => 'عملية: استئصال الزائدة', 'is_operation' => true]);
    }

    public function test_generated_invoice_persists_operation_line_items(): void
    {
        $cashier = User::factory()->role('cashier')->create();
        $admission = Admission::factory()->create();
        $procedure = Procedure::factory()->create(['name_ar' => 'عملية قلب مفتوح']);
        Operation::factory()->for($admission)->create(['procedure_id' => $procedure->id, 'price' => 500000]);

        $response = $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/invoices");

        $response->assertCreated()->assertJsonPath('total', '500000.00');
        $this->assertDatabaseHas('invoice_items', [
            'description' => 'عملية: عملية قلب مفتوح',
            'quantity' => 1,
            'unit_price' => 500000,
            'total' => 500000,
        ]);
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
