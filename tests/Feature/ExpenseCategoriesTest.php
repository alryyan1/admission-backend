<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_distinct_categories_already_used_on_expenses(): void
    {
        $user = User::factory()->role('cashier')->create();
        Expense::factory()->create(['category' => 'صيانة']);
        Expense::factory()->create(['category' => 'صيانة']);
        Expense::factory()->create(['category' => 'إيجار']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/expenses/categories');

        $response->assertOk();
        $this->assertSame(['إيجار', 'صيانة'], $response->json());
    }

    public function test_it_is_forbidden_for_roles_outside_admin_and_cashier(): void
    {
        $nurse = User::factory()->role('nurse')->create();

        $this->actingAs($nurse, 'sanctum')->getJson('/api/expenses/categories')->assertForbidden();
    }
}
