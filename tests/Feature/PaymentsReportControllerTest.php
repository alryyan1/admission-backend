<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_report_sums_deposits_within_range(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $cash = PaymentMethod::factory()->create(['name' => 'نقدي']);
        AdmissionDeposit::factory()->for($admission)->create(['amount' => 1000, 'payment_method_id' => $cash->id, 'paid_at' => now()]);
        AdmissionDeposit::factory()->for($admission)->create(['amount' => 2000, 'payment_method_id' => $cash->id, 'paid_at' => now()]);
        AdmissionDeposit::factory()->for($admission)->create(['amount' => 5000, 'paid_at' => now()->subDays(60)]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/reports/payments');

        $response->assertOk();
        $response->assertJsonPath('summary.count', 2);
        $response->assertJsonPath('summary.total_amount', 3000);
        $response->assertJsonFragment(['method' => 'نقدي', 'count' => 2, 'total' => 3000]);
    }

    public function test_payments_report_filters_by_payment_method(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $cash = PaymentMethod::factory()->create();
        $bank = PaymentMethod::factory()->create();
        AdmissionDeposit::factory()->for($admission)->create(['amount' => 1000, 'payment_method_id' => $cash->id, 'paid_at' => now()]);
        AdmissionDeposit::factory()->for($admission)->create(['amount' => 4000, 'payment_method_id' => $bank->id, 'paid_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/reports/payments?payment_method_id={$bank->id}");

        $response->assertOk();
        $response->assertJsonPath('summary.count', 1);
        $response->assertJsonPath('summary.total_amount', 4000);
    }

    public function test_payments_report_requires_authentication(): void
    {
        $this->getJson('/api/reports/payments')->assertUnauthorized();
    }

    public function test_payments_report_pdf(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        AdmissionDeposit::factory()->for($admission)->create(['amount' => 1000, 'paid_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')->get('/api/reports/payments.pdf');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
