<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Operation;
use App\Models\Procedure;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PdfDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function assertPdf(TestResponse $response): void
    {
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    private function admissionWithActivity(): Admission
    {
        $admission = Admission::factory()->create(['admission_date' => now()->subDays(2)]);
        $admission->requestedServices()->create(['name' => 'أشعة صدر', 'quantity' => 2, 'unit_price' => 10000]);
        $procedure = Procedure::factory()->create(['name_ar' => 'استئصال الزائدة']);
        Operation::factory()->for($admission)->create(['procedure_id' => $procedure->id, 'price' => 90000]);
        $admission->deposits()->create(['amount' => 15000, 'paid_at' => now()]);

        return $admission;
    }

    public function test_deposit_receipt_pdf(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $deposit = $admission->deposits()->create(['amount' => 2000, 'paid_at' => now()]);

        $this->assertPdf(
            $this->actingAs($user, 'sanctum')
                ->get("/api/admissions/{$admission->id}/deposits/{$deposit->id}/receipt.pdf")
        );
    }

    public function test_deposit_receipt_rejects_mismatched_admission(): void
    {
        $user = User::factory()->create();
        $admissionA = Admission::factory()->create();
        $admissionB = Admission::factory()->create();
        $deposit = $admissionA->deposits()->create(['amount' => 2000, 'paid_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->get("/api/admissions/{$admissionB->id}/deposits/{$deposit->id}/receipt.pdf")
            ->assertNotFound();
    }

    public function test_admission_preliminary_invoice_pdf(): void
    {
        $user = User::factory()->create();
        $admission = $this->admissionWithActivity();

        $this->assertPdf(
            $this->actingAs($user, 'sanctum')->get("/api/admissions/{$admission->id}/invoice.pdf")
        );
    }

    public function test_final_invoice_pdf(): void
    {
        $user = User::factory()->create();
        $admission = $this->admissionWithActivity();
        $invoice = app(InvoiceService::class)->generate($admission, $user);

        $this->assertPdf(
            $this->actingAs($user, 'sanctum')->get("/api/invoices/{$invoice->id}/invoice.pdf")
        );
    }

    public function test_account_statement_pdf(): void
    {
        $user = User::factory()->create();
        $admission = $this->admissionWithActivity();

        $this->assertPdf(
            $this->actingAs($user, 'sanctum')->get("/api/admissions/{$admission->id}/account-statement.pdf")
        );
    }

    public function test_admission_summary_pdf(): void
    {
        $user = User::factory()->create();
        $admission = $this->admissionWithActivity();

        $this->assertPdf(
            $this->actingAs($user, 'sanctum')->get("/api/admissions/{$admission->id}/summary.pdf")
        );
    }

    public function test_operation_invoice_pdf(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $operation = Operation::factory()->for($admission)->create(['price' => 50000]);

        $this->assertPdf(
            $this->actingAs($user, 'sanctum')->get("/api/operations/{$operation->id}/invoice.pdf")
        );
    }

    public function test_operation_invoice_requires_price(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $operation = Operation::factory()->for($admission)->create(['price' => null]);

        $this->actingAs($user, 'sanctum')
            ->get("/api/operations/{$operation->id}/invoice.pdf")
            ->assertStatus(422);
    }

    public function test_operation_team_pdf(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $operation = Operation::factory()->for($admission)->create(['price' => 50000]);
        $operation->teamMembers()->create(['name' => 'جراح', 'entitlement_amount' => 20000]);

        $this->assertPdf(
            $this->actingAs($user, 'sanctum')->get("/api/operations/{$operation->id}/team.pdf")
        );
    }

    public function test_operation_team_pdf_without_members_or_price(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $operation = Operation::factory()->for($admission)->create(['price' => null]);

        $this->assertPdf(
            $this->actingAs($user, 'sanctum')->get("/api/operations/{$operation->id}/team.pdf")
        );
    }

    public function test_pdf_endpoints_require_authentication(): void
    {
        $admission = Admission::factory()->create();

        $this->getJson("/api/admissions/{$admission->id}/summary.pdf")->assertUnauthorized();
    }
}
