<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_totals_grouped_by_category_and_payment_method(): void
    {
        $user = User::factory()->role('cashier')->create();
        $bank = PaymentMethod::factory()->create(['name' => 'بنكك']);

        Expense::factory()->create([
            'expense_date' => '2026-02-10',
            'category' => 'إيجار',
            'amount' => 1000,
            'payment_method_id' => $bank->id,
        ]);
        Expense::factory()->create([
            'expense_date' => '2026-02-12',
            'category' => 'إيجار',
            'amount' => 500,
            'payment_method_id' => null,
        ]);
        Expense::factory()->create([
            'expense_date' => '2026-02-15',
            'category' => 'صيانة',
            'amount' => 300,
            'payment_method_id' => $bank->id,
        ]);
        // Outside the requested range, must not be counted.
        Expense::factory()->create(['expense_date' => '2026-03-01', 'category' => 'صيانة', 'amount' => 999]);

        $response = $this->actingAs($user, 'sanctum')->getJson(
            '/api/reports/expenses?from=2026-02-01&to=2026-02-28'
        );

        $response->assertOk();
        $response->assertJsonPath('summary.count', 3);
        $response->assertJsonPath('summary.total_amount', 1800);
        $response->assertJsonPath('by_category.0.category', 'إيجار');
        $response->assertJsonPath('by_category.0.total', 1500);
        $response->assertJsonPath('by_category.1.category', 'صيانة');
        $response->assertJsonPath('by_category.1.total', 300);
        $response->assertJsonPath('by_method.0.method', 'بنكك');
        $response->assertJsonPath('by_method.0.total', 1300);
    }

    public function test_it_filters_by_category_and_description_search(): void
    {
        $user = User::factory()->role('cashier')->create();
        Expense::factory()->create(['expense_date' => '2026-02-10', 'category' => 'إيجار', 'description' => 'إيجار الطابق الأول']);
        Expense::factory()->create(['expense_date' => '2026-02-10', 'category' => 'صيانة', 'description' => 'إصلاح مكيف']);

        $response = $this->actingAs($user, 'sanctum')->getJson(
            '/api/reports/expenses?from=2026-02-01&to=2026-02-28&category=إيجار'
        );

        $response->assertOk()->assertJsonPath('summary.count', 1);
        $this->assertSame('إيجار', $response->json('expenses.0.category'));
    }

    public function test_it_is_forbidden_for_roles_outside_admin_and_cashier(): void
    {
        $nurse = User::factory()->role('nurse')->create();

        $this->actingAs($nurse, 'sanctum')->getJson('/api/reports/expenses')->assertForbidden();
    }
}
