<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelAdmissionRequest;
use App\Http\Requests\DischargeAdmissionRequest;
use App\Http\Requests\StoreAdmissionRequest;
use App\Models\Admission;
use App\Services\AdmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdmissionController extends Controller
{
    public function __construct(private readonly AdmissionService $admissionService) {}

    /**
     * @return array<int|string, mixed>
     */
    private function showRelations(): array
    {
        return [
            'patient',
            'bed.room.ward.floor',
            'admittingDoctor',
            'admittedBy',
            'dischargedBy',
            'cancelledBy',
            'vitalSigns' => fn ($query) => $query->latest('recorded_at'),
            'doctorOrders.orderedBy',
            'deposits',
            'requestedServices',
            'invoices',
            'operations.surgeon',
            'operations.operationRoom',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Admission::with(['patient', 'bed.room.ward.floor', 'admittingDoctor']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->latest('admission_date')->paginate($request->integer('per_page', 15)));
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
