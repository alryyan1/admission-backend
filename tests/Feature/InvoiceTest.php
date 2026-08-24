<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_an_invoice_persists_a_snapshot_matching_the_live_calculation(): void
    {
        $cashier = User::factory()->role('cashier')->create();
        $room = Room::factory()->create(['price_per_day' => 50000]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $admission = Admission::factory()->create([
            'bed_id' => $bed->id,
            'admission_date' => now()->subDays(2),
        ]);
        $admission->requestedServices()->create(['name' => 'أشعة', 'quantity' => 2, 'unit_price' => 10000]);

        $response = $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/invoices");

        $response->assertCreated();
        $response->assertJsonPath('total', '20000.00');
        $response->assertJsonPath('status', 'issued');
        $response->assertJsonCount(1, 'items');
        $this->assertMatchesRegularExpression('/^INV-\d{2}-\d{6}$/', $response->json('invoice_number'));

        $this->assertDatabaseHas('invoices', [
            'admission_id' => $admission->id,
            'total' => 20000,
            'created_by' => $cashier->id,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'description' => 'أشعة',
            'quantity' => 2,
            'unit_price' => 10000,
            'total' => 20000,
        ]);
    }

    public function test_a_non_cashier_cannot_generate_an_invoice(): void
    {
        $nurse = User::factory()->role('nurse')->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/invoices");

        $response->assertForbidden();
    }

    public function test_listing_invoices_for_an_admission(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        Invoice::factory()->for($admission)->has(InvoiceItem::factory()->count(2), 'items')->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admissions/{$admission->id}/invoices");

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_marking_an_invoice_paid(): void
    {
        $cashier = User::factory()->role('cashier')->create();
        $admission = Admission::factory()->create();
        $invoice = Invoice::factory()->for($admission)->create(['status' => 'issued']);

        $response = $this->actingAs($cashier, 'sanctum')
            ->patchJson("/api/invoices/{$invoice->id}/pay");

        $response->assertOk()->assertJsonPath('status', 'paid');
        $this->assertNotNull($response->json('paid_at'));
    }
}
