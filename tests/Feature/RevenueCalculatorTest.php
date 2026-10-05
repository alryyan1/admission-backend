<?php

namespace Tests\Feature;

use App\Models\AdmissionDeposit;
use App\Models\Expense;
use App\Models\Operation;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_calculator_breaks_down_revenue_and_expenses_by_payment_method(): void
    {
        $user = User::factory()->create();
        $cash = PaymentMethod::factory()->create(['name' => 'نقدي']);
        $bank = PaymentMethod::factory()->create(['name' => 'بنكك']);

        AdmissionDeposit::factory()->create(['payment_method_id' => $cash->id, 'amount' => 1000, 'paid_at' => now()]);
        AdmissionDeposit::factory()->create(['payment_method_id' => $bank->id, 'amount' => 2000, 'paid_at' => now()]);
        AdmissionDeposit::factory()->create(['payment_method_id' => $cash->id, 'amount' => 9000, 'paid_at' => now()->subDay()]);

        Expense::query()->create([
            'payment_method_id' => $cash->id,
            'amount' => 300,
            'category' => 'صيانة',
            'description' => 'صيانة',
            'expense_date' => now(),
        ]);

        $operation = Operation::factory()->create();
        $operation->teamMembers()->create([
            'payment_method_id' => $cash->id,
            'entitlement_amount' => 150,
            'entitlement_paid_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/reports/revenue-calculator');

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(['نقدي', 'بنكك'], $data['payment_methods']);

        $rowsByLabel = collect($data['rows'])->keyBy('label');

        $this->assertEquals(1000.0, $rowsByLabel['الإيرادات']['amounts']['نقدي']);
        $this->assertEquals(2000.0, $rowsByLabel['الإيرادات']['amounts']['بنكك']);
        $this->assertEquals(3000.0, $rowsByLabel['الإيرادات']['total']);

        $this->assertEquals(300.0, $rowsByLabel['المصروفات']['amounts']['نقدي']);
        $this->assertEquals(300.0, $rowsByLabel['المصروفات']['total']);

        $this->assertEquals(150.0, $rowsByLabel['استحقاقات العميلة']['amounts']['نقدي']);
        $this->assertEquals(150.0, $rowsByLabel['استحقاقات العميلة']['total']);

        $this->assertEquals(550.0, $rowsByLabel['الصافي']['amounts']['نقدي']);
        $this->assertEquals(2000.0, $rowsByLabel['الصافي']['amounts']['بنكك']);
        $this->assertEquals(2550.0, $rowsByLabel['الصافي']['total']);
    }

    public function test_revenue_calculator_ignores_inactive_payment_methods(): void
    {
        $user = User::factory()->create();
        PaymentMethod::factory()->create(['name' => 'نقدي', 'is_active' => true]);
        PaymentMethod::factory()->create(['name' => 'محفوظ', 'is_active' => false]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/reports/revenue-calculator');

        $response->assertOk();
        $this->assertSame(['نقدي'], $response->json('payment_methods'));
    }

    public function test_revenue_calculator_pdf_renders(): void
    {
        $user = User::factory()->create();
        PaymentMethod::factory()->create(['name' => 'نقدي']);

        $response = $this->actingAs($user, 'sanctum')->get('/api/reports/revenue-calculator.pdf');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_revenue_calculator_filters_revenue_and_expenses_by_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $cash = PaymentMethod::factory()->create(['name' => 'نقدي']);

        AdmissionDeposit::factory()->create(['payment_method_id' => $cash->id, 'amount' => 1000, 'paid_by' => $user->id, 'paid_at' => now()]);
        AdmissionDeposit::factory()->create(['payment_method_id' => $cash->id, 'amount' => 5000, 'paid_by' => $otherUser->id, 'paid_at' => now()]);

        Expense::query()->create(['payment_method_id' => $cash->id, 'amount' => 200, 'category' => 'صيانة', 'description' => 'صيانة', 'expense_date' => now(), 'recorded_by_id' => $user->id]);
        Expense::query()->create(['payment_method_id' => $cash->id, 'amount' => 700, 'category' => 'صيانة', 'description' => 'صيانة', 'expense_date' => now(), 'recorded_by_id' => $otherUser->id]);

        $operation = Operation::factory()->create();
        $operation->teamMembers()->create(['payment_method_id' => $cash->id, 'entitlement_amount' => 150, 'entitlement_paid_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/reports/revenue-calculator?user_id={$user->id}");

        $response->assertOk();
        $rowsByLabel = collect($response->json('rows'))->keyBy('label');

        $this->assertEquals(1000.0, $rowsByLabel['الإيرادات']['total']);
        $this->assertEquals(200.0, $rowsByLabel['المصروفات']['total']);
        $this->assertEquals(0.0, $rowsByLabel['استحقاقات العميلة']['total']);
        $this->assertEquals(800.0, $rowsByLabel['الصافي']['total']);
    }

    public function test_revenue_calculator_without_user_filter_includes_everyone(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $cash = PaymentMethod::factory()->create(['name' => 'نقدي']);

        AdmissionDeposit::factory()->create(['payment_method_id' => $cash->id, 'amount' => 1000, 'paid_by' => $user->id, 'paid_at' => now()]);
        AdmissionDeposit::factory()->create(['payment_method_id' => $cash->id, 'amount' => 5000, 'paid_by' => $otherUser->id, 'paid_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/reports/revenue-calculator');

        $rowsByLabel = collect($response->json('rows'))->keyBy('label');
        $this->assertEquals(6000.0, $rowsByLabel['الإيرادات']['total']);
    }

    public function test_revenue_calculator_pdf_renders_for_selected_user(): void
    {
        $user = User::factory()->create(['name' => 'كاشير']);
        PaymentMethod::factory()->create(['name' => 'نقدي']);

        $response = $this->actingAs($user, 'sanctum')->get("/api/reports/revenue-calculator.pdf?user_id={$user->id}");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_revenue_calculator_requires_authentication(): void
    {
        $this->getJson('/api/reports/revenue-calculator')->assertUnauthorized();
    }
}
