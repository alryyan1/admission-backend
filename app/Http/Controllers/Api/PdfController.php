<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\Invoice;
use App\Models\Operation;
use App\Services\PdfDocumentService;
use Illuminate\Http\Response;
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

    public function accountStatement(Admission $admission): Response
    {
        return $this->documents->accountStatement($admission);
    }

    public function admissionSummary(Admission $admission): Response
    {
        return $this->documents->admissionSummary($admission);
    }
}
