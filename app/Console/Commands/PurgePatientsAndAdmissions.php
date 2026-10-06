<?php

namespace App\Console\Commands;

use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\DoctorOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Operation;
use App\Models\OperationSupply;
use App\Models\OperationTeamMember;
use App\Models\Patient;
use App\Models\RequestedService;
use App\Models\TreatmentDose;
use App\Models\VitalSign;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurgePatientsAndAdmissions extends Command
{
    protected $signature = 'patients:purge {--force : Skip the confirmation prompt}';

    protected $description = 'Delete all patients, admissions, and their related rows (invoices, orders, operations, deposits, vitals, requested services)';

    /**
     * Child tables first, so restrictOnDelete foreign keys never block a parent delete.
     *
     * @var array<int, class-string<Model>>
     */
    private const DELETION_ORDER = [
        InvoiceItem::class,
        Invoice::class,
        TreatmentDose::class,
        DoctorOrder::class,
        AdmissionDeposit::class,
        RequestedService::class,
        VitalSign::class,
        OperationSupply::class,
        OperationTeamMember::class,
        Operation::class,
        Admission::class,
        Patient::class,
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This permanently deletes ALL patients and admissions with their related rows. Continue?')) {
            $this->info('Aborted. Nothing was deleted.');

            return self::SUCCESS;
        }

        $deletedCounts = DB::transaction(function (): array {
            $counts = [];

            foreach (self::DELETION_ORDER as $modelClass) {
                $counts[class_basename($modelClass)] = $modelClass::query()->delete();
            }

            return $counts;
        });

        foreach ($deletedCounts as $modelName => $count) {
            $this->line("{$modelName}: {$count} deleted");
        }

        $this->info('Patients and admissions purged.');

        return self::SUCCESS;
    }
}
