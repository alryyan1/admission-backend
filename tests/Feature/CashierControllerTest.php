<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_view_admissions_with_computed_balances(): void
    {
        $cashier = User::factory()->role('cashier')->create();

        $admission = Admission::factory()->create(['status' => 'admitted']);
        $admission->requestedServices()->create(['name' => 'أشعة', 'quantity' => 2, 'unit_price' => 10000]);
        $admission->deposits()->create(['amount' => 5000, 'paid_at' => now()]);

        Admission::factory()->create(['status' => 'discharged']);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/cashier/admissions');

        $response->assertOk();
        $response->assertJsonCount(1, 'admissions');
        $response->assertJsonPath('admissions.0.id', $admission->id);
        $response->assertJsonPath('admissions.0.services_total', 20000);
        $response->assertJsonPath('admissions.0.deposits_total', 5000);
        $response->assertJsonPath('admissions.0.balance_due', 15000);
        $response->assertJsonPath('summary.admissions_count', 1);
        $response->assertJsonPath('summary.total_outstanding', 15000);
    }

    public function test_nurse_cannot_view_cashier_overview(): void
    {
        $nurse = User::factory()->role('nurse')->create();

        $response = $this->actingAs($nurse, 'sanctum')->getJson('/api/cashier/admissions');

        $response->assertForbidden();
    }
}
