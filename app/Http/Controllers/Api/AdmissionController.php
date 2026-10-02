<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelAdmissionRequest;
use App\Http\Requests\DischargeAdmissionRequest;
use App\Http\Requests\StoreAdmissionRequest;
use App\Http\Requests\UpdateAdmissionRequest;
use App\Models\Admission;
use App\Services\AdmissionService;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdmissionController extends Controller
{
    public function __construct(
        private readonly AdmissionService $admissionService,
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * @return array<int|string, mixed>
     */
    private function showRelations(): array
    {
        return [
            'patient',
            'bed.room.ward.floor',
            'admittedBy',
            'dischargedBy',
            'cancelledBy',
            'admittingDoctor',
            'referredByDoctor',
            'vitalSigns' => fn ($query) => $query->latest('recorded_at'),
            'doctorOrders.orderedBy',
            'deposits.paymentMethod',
            'requestedServices',
            'invoices',
            'operations.teamMembers.doctor',
            'operations.teamMembers.role',
            'operations.supplies',
            'operations.procedure.category',
            'operations.surgeon',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Admission::with([
            'patient', 'bed.room.ward.floor', 'admittingDoctor', 'referredByDoctor',
            'requestedServices', 'operations.procedure', 'deposits',
        ])->withCount('operations');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('patient', fn ($patientQuery) => $patientQuery->where('name', 'like', "%{$search}%"));
        }

        if ($request->filled('admission_id')) {
            $query->where('id', $request->integer('admission_id'));
        }

        if ($request->filled('bed_id')) {
            $query->where('bed_id', $request->integer('bed_id'));
        } elseif ($request->filled('room_id')) {
            $roomId = $request->integer('room_id');
            $query->whereHas('bed', fn ($bedQuery) => $bedQuery->where('room_id', $roomId));
        }

        if ($request->filled('from')) {
            $query->where('admission_date', '>=', Carbon::parse($request->query('from')));
        }

        if ($request->filled('to')) {
            $query->where('admission_date', '<=', Carbon::parse($request->query('to')));
        }

        $admissions = $query->latest('admission_date')->paginate($request->integer('per_page', 15));

        $admissions->getCollection()->each(function (Admission $admission): void {
            $charges = $this->invoiceService->previewCharges($admission);
            $admission->total_charges = $charges['total'];
            $admission->paid_total = $charges['deposits_total'];
            $admission->balance_due = $charges['balance_due'];
        });

        return response()->json($admissions);
    }

    public function store(StoreAdmissionRequest $request): JsonResponse
    {
        $admission = $this->admissionService->admit($request->validated(), $request->user());

        return response()->json($admission->load($this->showRelations()), Response::HTTP_CREATED);
    }

    public function show(Admission $admission): JsonResponse
    {
        return response()->json($admission->load($this->showRelations()));
    }

    public function update(UpdateAdmissionRequest $request, Admission $admission): JsonResponse
    {
        $admission->assertMutable($request->user());
        $admission->update($request->validated());

        return response()->json($admission->load($this->showRelations()));
    }

    public function discharge(DischargeAdmissionRequest $request, Admission $admission): JsonResponse
    {
        $admission = $this->admissionService->discharge($admission, $request->validated(), $request->user());

        return response()->json($admission->load($this->showRelations()));
    }

    public function cancel(CancelAdmissionRequest $request, Admission $admission): JsonResponse
    {
        $admission = $this->admissionService->cancel($admission, $request->validated(), $request->user());

        return response()->json($admission->load($this->showRelations()));
    }
}
