<?php

namespace Tests\Feature;

use App\Models\AdmissionDeposit;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyRevenueReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_revenue_lists_every_day_of_the_month_with_amounts_per_payment_method(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $cash = PaymentMethod::factory()->create(['name' => 'نقدي']);
        $card = PaymentMethod::factory()->create(['name' => 'بطاقة']);

        AdmissionDeposit::factory()->create(['payment_method_id' => $cash->id, 'amount' => 1000, 'paid_at' => '2026-10-03 09:00:00']);
        AdmissionDeposit::factory()->create(['payment_method_id' => $card->id, 'amount' => 500, 'paid_at' => '2026-10-03 18:00:00']);
        AdmissionDeposit::factory()->create(['payment_method_id' => $cash->id, 'amount' => 300, 'paid_at' => '2026-10-05 12:00:00']);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/daily-revenue?month=2026-10');

        $response->assertOk()
            ->assertJsonPath('month', '2026-10')
            ->assertJsonPath('range.from', '2026-10-01')
            ->assertJsonPath('range.to', '2026-10-31')
            ->assertJsonCount(31, 'days')
            ->assertJsonCount(2, 'payment_methods')
            ->assertJsonPath('payment_methods.0.name', 'بطاقة')
            ->assertJsonPath('payment_methods.1.name', 'نقدي');

        $days = collect($response->json('days'))->keyBy('date');

        $this->assertEquals(1500.0, $days['2026-10-03']['total']);
        $this->assertEquals(1000.0, $days['2026-10-03']['amounts']['method_'.$cash->id]);
        $this->assertEquals(500.0, $days['2026-10-03']['amounts']['method_'.$card->id]);
        $this->assertEquals(0.0, $days['2026-10-04']['total']);
        $this->assertEquals(300.0, $days['2026-10-05']['amounts']['method_'.$cash->id]);
        $this->assertEquals(1800.0, $response->json('totals.total'));
        $this->assertEquals(1300.0, $response->json('totals.amounts.method_'.$cash->id));
        $this->assertEquals(500.0, $response->json('totals.amounts.method_'.$card->id));
    }

    public function test_deposits_outside_the_month_are_excluded(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $cash = PaymentMethod::factory()->create(['name' => 'نقدي']);

        AdmissionDeposit::factory()->create(['payment_method_id' => $cash->id, 'amount' => 700, 'paid_at' => '2026-09-30 23:00:00']);
        AdmissionDeposit::factory()->create(['payment_method_id' => $cash->id, 'amount' => 900, 'paid_at' => '2026-11-01 00:30:00']);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/daily-revenue?month=2026-10');

        $response->assertOk()
            ->assertJsonCount(0, 'payment_methods')
            ->assertJsonPath('totals.total', 0);
    }

    public function test_deposits_without_a_payment_method_are_grouped_as_unassigned(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        AdmissionDeposit::factory()->create(['payment_method_id' => null, 'amount' => 250, 'paid_at' => '2026-10-10 10:00:00']);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/daily-revenue?month=2026-10');

        $response->assertOk()
            ->assertJsonPath('payment_methods.0.key', 'unassigned')
            ->assertJsonPath('payment_methods.0.name', 'غير محدد');

        $days = collect($response->json('days'))->keyBy('date');
        $this->assertEquals(250.0, $days['2026-10-10']['amounts']['unassigned']);
        $this->assertEquals(250.0, $days['2026-10-10']['total']);
    }

    public function test_daily_revenue_defaults_to_the_current_month(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/daily-revenue');

        $response->assertOk()
            ->assertJsonPath('month', now()->format('Y-m'))
            ->assertJsonCount(now()->daysInMonth, 'days');
    }

    public function test_daily_revenue_rejects_an_invalid_month(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($cashier, 'sanctum')->getJson('/api/reports/daily-revenue?month=2026-13');

        $response->assertUnprocessable()->assertJsonValidationErrors(['month']);
    }

    public function test_doctor_cannot_view_daily_revenue(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        $response = $this->actingAs($doctor, 'sanctum')->getJson('/api/reports/daily-revenue?month=2026-10');

        $response->assertForbidden();
    }
}
