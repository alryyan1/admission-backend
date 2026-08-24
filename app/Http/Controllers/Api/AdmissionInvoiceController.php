<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;

class AdmissionInvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function show(Admission $admission): JsonResponse
    {
        $admission->loadMissing('patient');
        $charges = $this->invoiceService->previewCharges($admission);

        return response()->json([
            'admission_id' => $admission->id,
            'patient' => $admission->patient,
            'requested_services' => $charges['requested_services'],
            'services_total' => $charges['services_total'],
            'total' => $charges['total'],
            'deposits' => $charges['deposits'],
            'deposits_total' => $charges['deposits_total'],
            'balance_due' => $charges['balance_due'],
        ]);
    }
}
