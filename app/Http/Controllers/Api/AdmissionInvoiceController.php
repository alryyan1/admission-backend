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
            'billing_mode' => $charges['billing_mode'],
            'nights_stayed' => $charges['nights_stayed'],
            'price_per_day' => $charges['price_per_day'],
            'admission_duration_hours' => $charges['admission_duration_hours'],
            'bed_charges' => $charges['bed_charges'],
            'requested_services' => $charges['requested_services'],
            'services_total' => $charges['services_total'],
            'total' => $charges['total'],
            'deposits' => $charges['deposits'],
            'deposits_total' => $charges['deposits_total'],
            'balance_due' => $charges['balance_due'],
        ]);
    }
}
