<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\Invoice;
use App\Models\Operation;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\DailyRevenueReportService;
use App\Services\OperationService;
use App\Services\PdfDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PdfController extends Controller
{
    public function __construct(private readonly PdfDocumentService $documents) {}

    public function depositReceipt(Admission $admission, AdmissionDeposit $deposit): Response
    {
        abort_unless($deposit->admission_id === $admission->id, HttpResponse::HTTP_NOT_FOUND);

        return $this->documents->depositReceipt($deposit);
    }

    public function admissionInvoice(Admission $admission): Response
    {
        return $this->documents->admissionInvoice($admission);
    }

    public function finalInvoice(Invoice $invoice): Response
    {
        return $this->documents->finalInvoice($invoice);
    }

    public function operationInvoice(Operation $operation): Response
    {
        abort_if($operation->price === null, HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'حدّد سعر العملية أولاً.');

        return $this->documents->operationInvoice($operation);
    }

    public function operationTeam(Operation $operation): Response
    {
        return $this->documents->operationTeam($operation);
    }

    public function accountStatement(Admission $admission): Response
    {
        return $this->documents->accountStatement($admission);
    }

    public function admissionSummary(Admission $admission): Response
    {
        return $this->documents->admissionSummary($admission);
    }

    public function revenueCalculator(Request $request): Response
    {
        $date = $request->filled('date') ? Carbon::parse($request->query('date')) : now();

        $userId = $request->filled('user_id') ? $request->integer('user_id') : null;
        $userName = $userId !== null ? User::query()->whereKey($userId)->value('name') : null;

        return $this->documents->revenueCalculator($date, $request->user()?->name, $userId, $userName);
    }

    public function dailyRevenueReport(Request $request, DailyRevenueReportService $report): Response
    {
        $data = $report->build($request->filled('month') ? $request->string('month')->toString() : null);

        return $this->documents->dailyRevenueReport($data);
    }

    public function operationsReport(Request $request, OperationService $operations): Response
    {
        $list = $operations->filteredQuery($request)
            ->with(['procedure', 'surgeon', 'admission.patient', 'teamMembers'])
            ->latest('scheduled_at')
            ->get();

        return $this->documents->operationsReport(
            $list,
            $request->filled('date_from') ? (string) $request->query('date_from') : null,
            $request->filled('date_to') ? (string) $request->query('date_to') : null,
        );
    }

    public function admissionsPaymentsReport(Request $request, AdmissionService $admissions): Response
    {
        $list = $admissions->filteredQuery($request)
            ->with(['patient.admittingDoctor', 'bed.room.ward.floor', 'requestedServices', 'deposits'])
            ->latest('admission_date')
            ->get();

        return $this->documents->admissionsPaymentsReport(
            $list,
            $request->filled('from') ? (string) $request->query('from') : null,
            $request->filled('to') ? (string) $request->query('to') : null,
        );
    }
}
