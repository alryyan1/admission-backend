<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\DepositSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_is_allowed_without_balance_when_setting_disabled(): void
    {
        $cashier = User::factory()->role('cashier')->permissions([User::PERMISSION_ADD_DIRECT_DEPOSIT])->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/deposits", ['amount' => 5000]);

        $response->assertCreated();
        $this->assertDatabaseHas('admission_deposits', ['admission_id' => $admission->id, 'amount' => 5000]);
    }

    public function test_deposit_is_rejected_without_balance_when_setting_enabled(): void
    {
        DepositSetting::current()->update(['require_balance_for_deposit' => true]);

        $cashier = User::factory()->role('cashier')->permissions([User::PERMISSION_ADD_DIRECT_DEPOSIT])->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/deposits", ['amount' => 5000]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('amount');
        $this->assertDatabaseMissing('admission_deposits', ['admission_id' => $admission->id]);
    }

    public function test_deposit_is_allowed_with_balance_when_setting_enabled(): void
    {
        DepositSetting::current()->update(['require_balance_for_deposit' => true]);

        $cashier = User::factory()->role('cashier')->permissions([User::PERMISSION_ADD_DIRECT_DEPOSIT])->create();
        $admission = Admission::factory()->create();
        $admission->requestedServices()->create(['name' => 'أشعة', 'quantity' => 1, 'unit_price' => 10000]);

        $response = $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/deposits", ['amount' => 5000]);

        $response->assertCreated();
        $this->assertDatabaseHas('admission_deposits', ['admission_id' => $admission->id, 'amount' => 5000]);
    }

    public function test_deposit_is_rejected_once_balance_is_fully_covered(): void
    {
        DepositSetting::current()->update(['require_balance_for_deposit' => true]);

        $cashier = User::factory()->role('cashier')->permissions([User::PERMISSION_ADD_DIRECT_DEPOSIT])->create();
        $admission = Admission::factory()->create();
        $admission->requestedServices()->create(['name' => 'أشعة', 'quantity' => 1, 'unit_price' => 10000]);
        $admission->deposits()->create(['amount' => 10000, 'paid_at' => now()]);

        $response = $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/deposits", ['amount' => 5000]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('amount');
    }

    public function test_admin_without_the_direct_deposit_permission_cannot_record_a_deposit(): void
    {
        $admin = User::factory()->role('admin')->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/deposits", ['amount' => 5000]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('admission_deposits', ['admission_id' => $admission->id]);
    }

    public function test_admin_with_the_direct_deposit_permission_can_record_a_deposit(): void
    {
        $admin = User::factory()->role('admin')->permissions([User::PERMISSION_ADD_DIRECT_DEPOSIT])->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/deposits", ['amount' => 5000]);

        $response->assertCreated();
        $this->assertDatabaseHas('admission_deposits', ['admission_id' => $admission->id, 'amount' => 5000]);
    }
}
