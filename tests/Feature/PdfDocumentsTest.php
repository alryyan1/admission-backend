<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\FacilitySetting;
use App\Models\Operation;
use App\Models\Procedure;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_deposit_receipt_pdf_survives_a_corrupted_facility_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('facility/corrupt-logo.png', 'not actually a png');
        FacilitySetting::current()->update(['logo_path' => 'facility/corrupt-logo.png', 'use_logo' => true]);

        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $deposit = $admission->deposits()->create(['amount' => 2000, 'paid_at' => now()]);

        $this->assertPdf(
            $this->actingAs($user, 'sanctum')
                ->get("/api/admissions/{$admission->id}/deposits/{$deposit->id}/receipt.pdf")
        );
    }

    public function test_deposit_receipt_pdf_embeds_the_facility_logo_regardless_of_working_directory(): void
    {
        // TCPDF's file-access sandbox trusts local image reads under
        // getcwd() among other candidates. A CLI process's cwd is usually
        // the project root (which happens to cover storage/app/public), but
        // a real web server's isn't — this reproduces that by changing cwd
        // to something that doesn't cover the storage path at all.
        $originalCwd = getcwd();
        chdir(sys_get_temp_dir());

        try {
            Storage::fake('public');
            $image = imagecreatetruecolor(150, 150);
            for ($x = 0; $x < 150; $x++) {
                for ($y = 0; $y < 150; $y++) {
                    imagesetpixel($image, $x, $y, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
                }
            }
            ob_start();
            imagepng($image, null, 0);
            $png = ob_get_clean();
            imagedestroy($image);
            Storage::disk('public')->put('facility/logo.png', $png);
            FacilitySetting::current()->update(['logo_path' => 'facility/logo.png', 'use_logo' => true]);

            $user = User::factory()->create();
            $admission = Admission::factory()->create();
            $deposit = $admission->deposits()->create(['amount' => 2000, 'paid_at' => now()]);

            $withLogo = $this->actingAs($user, 'sanctum')
                ->get("/api/admissions/{$admission->id}/deposits/{$deposit->id}/receipt.pdf");
            $this->assertPdf($withLogo);

            FacilitySetting::current()->update(['use_logo' => false]);
            $withoutLogo = $this->actingAs($user, 'sanctum')
                ->get("/api/admissions/{$admission->id}/deposits/{$deposit->id}/receipt.pdf");
            $this->assertPdf($withoutLogo);

            // An embedded logo meaningfully grows the PDF; this fails if the
            // image was silently skipped by the file-access sandbox.
            $this->assertGreaterThan(strlen($withoutLogo->getContent()) + 5000, strlen($withLogo->getContent()));
        } finally {
            chdir($originalCwd);
        }
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

    public function test_admission_summary_pdf_paginates_long_sections_and_shows_discharge_summary(): void
    {
        $user = User::factory()->create();
        $admission = $this->admissionWithActivity();
        $admission->update([
            'status' => 'discharged',
            'discharge_date' => now(),
            'discharge_summary' => 'تحسن المريض وخرج بحالة مستقرة.',
        ]);
        $admission->requestedServices()->createMany(
            array_fill(0, 80, ['name' => 'خدمة تجريبية', 'quantity' => 1, 'unit_price' => 1000])
        );

        $response = $this->actingAs($user, 'sanctum')->get("/api/admissions/{$admission->id}/summary.pdf");

        $this->assertPdf($response);
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page[^s]/', $response->getContent()));
    }

    public function test_admission_summary_pdf_for_cancelled_admission_without_activity(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create([
            'status' => 'cancelled',
            'cancellation_reason' => 'رفض المريض الدخول',
        ]);

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

    public function test_operations_report_pdf(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $operation = Operation::factory()->for($admission)->create(['price' => 50000]);
        $operation->teamMembers()->create(['name' => 'جراح', 'entitlement_amount' => 20000]);

        $this->assertPdf(
            $this->actingAs($user, 'sanctum')->get('/api/operations/report.pdf')
        );
    }

    public function test_operations_report_pdf_with_no_matching_operations(): void
    {
        $user = User::factory()->create();

        $this->assertPdf(
            $this->actingAs($user, 'sanctum')->get('/api/operations/report.pdf?search=no-such-procedure')
        );
    }

    public function test_pdf_endpoints_require_authentication(): void
    {
        $admission = Admission::factory()->create();

        $this->getJson("/api/admissions/{$admission->id}/summary.pdf")->assertUnauthorized();
    }
}
