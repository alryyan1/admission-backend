<?php

namespace Tests\Feature\Console;

use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\Bed;
use App\Models\Doctor;
use App\Models\DoctorOrder;
use App\Models\InsuranceCompany;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Operation;
use App\Models\OperationSupply;
use App\Models\OperationTeamMember;
use App\Models\Patient;
use App\Models\RequestedService;
use App\Models\TreatmentDose;
use App\Models\VitalSign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgePatientsAndAdmissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_deletes_patients_admissions_and_related_rows(): void
    {
        $admission = Admission::factory()->create();
        $invoice = Invoice::factory()->create(['admission_id' => $admission->id]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);
        $doctorOrder = DoctorOrder::factory()->create(['admission_id' => $admission->id]);
        TreatmentDose::factory()->create(['doctor_order_id' => $doctorOrder->id]);
        AdmissionDeposit::factory()->create(['admission_id' => $admission->id]);
        RequestedService::factory()->create(['admission_id' => $admission->id]);
        VitalSign::factory()->create(['admission_id' => $admission->id]);
        $operation = Operation::factory()->create(['admission_id' => $admission->id]);
        OperationSupply::factory()->create(['operation_id' => $operation->id]);
        OperationTeamMember::factory()->create(['operation_id' => $operation->id]);

        $this->artisan('patients:purge', ['--force' => true])
            ->expectsOutputToContain('Patients and admissions purged.')
            ->assertSuccessful();

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('admissions', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertDatabaseCount('doctor_orders', 0);
        $this->assertDatabaseCount('treatment_doses', 0);
        $this->assertDatabaseCount('admission_deposits', 0);
        $this->assertDatabaseCount('requested_services', 0);
        $this->assertDatabaseCount('vital_signs', 0);
        $this->assertDatabaseCount('operations', 0);
        $this->assertDatabaseCount('operation_supplies', 0);
        $this->assertDatabaseCount('operation_team_members', 0);
    }

    public function test_keeps_reference_data_such_as_doctors_beds_and_insurance_companies(): void
    {
        Admission::factory()->create();
        $doctorCount = Doctor::query()->count();
        $bedCount = Bed::query()->count();
        $insuranceCount = InsuranceCompany::query()->count();

        $this->artisan('patients:purge', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('doctors', $doctorCount);
        $this->assertDatabaseCount('beds', $bedCount);
        $this->assertDatabaseCount('insurance_companies', $insuranceCount);
    }

    public function test_aborts_without_deleting_when_confirmation_is_declined(): void
    {
        Patient::factory()->create();

        $this->artisan('patients:purge')
            ->expectsConfirmation('This permanently deletes ALL patients and admissions with their related rows. Continue?', 'no')
            ->expectsOutputToContain('Aborted. Nothing was deleted.')
            ->assertSuccessful();

        $this->assertDatabaseCount('patients', 1);
    }

    public function test_deletes_when_confirmation_is_accepted(): void
    {
        Patient::factory()->create();

        $this->artisan('patients:purge')
            ->expectsConfirmation('This permanently deletes ALL patients and admissions with their related rows. Continue?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseCount('patients', 0);
    }

    public function test_succeeds_when_there_is_nothing_to_delete(): void
    {
        $this->artisan('patients:purge', ['--force' => true])
            ->expectsOutputToContain('Patients and admissions purged.')
            ->assertSuccessful();

        $this->assertDatabaseCount('patients', 0);
    }
}
