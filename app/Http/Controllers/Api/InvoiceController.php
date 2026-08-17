<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index(Admission $admission): JsonResponse
    {
        return response()->json(
            $admission->invoices()->with('items', 'createdBy')->latest()->get()
        );
    }

    public function store(Request $request, Admission $admission): JsonResponse
    {
        $invoice = $this->invoiceService->generate($admission, $request->user());

        return response()->json($invoice->load('items', 'createdBy'), Response::HTTP_CREATED);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json($invoice->load('items', 'createdBy', 'admission.patient'));
    }

    public function markPaid(Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceService->markPaid($invoice);

        return response()->json($invoice->load('items', 'createdBy'));
    }
}
