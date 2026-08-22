<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelAdmissionRequest;
use App\Http\Requests\DischargeAdmissionRequest;
use App\Http\Requests\StoreAdmissionRequest;
use App\Models\Admission;
use App\Services\AdmissionService;
use App\Services\DoctorDirectory;
use Carbon\Carbon;
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
            'admittedBy',
            'dischargedBy',
            'cancelledBy',
            'vitalSigns' => fn ($query) => $query->latest('recorded_at'),
            'doctorOrders.orderedBy',
            'deposits',
            'requestedServices',
            'invoices',
            'operations.operationRoom',
            'operations.teamMembers',
            'operations.supplies',
            'operations.procedure.category',
        ];
    }

    private function attachDoctors(Admission $admission, DoctorDirectory $directory): Admission
    {
        $directory->attach($admission, 'admitting_doctor_id', 'admitting_doctor');
        $directory->attach($admission->operations, 'surgeon_id', 'surgeon');
        $directory->attach($admission->operations, 'requested_by_doctor_id', 'requested_by_doctor');

        foreach ($admission->operations as $operation) {
            $directory->attach($operation->teamMembers, 'doctor_id', 'doctor');
        }

        return $admission;
    }

    public function index(Request $request, DoctorDirectory $directory): JsonResponse
    {
        $query = Admission::with(['patient', 'bed.room.ward.floor'])->withCount('operations');

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
        $directory->attach($admissions, 'admitting_doctor_id', 'admitting_doctor');

        return response()->json($admissions);
    }

    public function store(StoreAdmissionRequest $request, DoctorDirectory $directory): JsonResponse
    {
        $admission = $this->admissionService->admit($request->validated(), $request->user());

        return response()->json($this->attachDoctors($admission->load($this->showRelations()), $directory), Response::HTTP_CREATED);
    }

    public function show(Admission $admission, DoctorDirectory $directory): JsonResponse
    {
        return response()->json($this->attachDoctors($admission->load($this->showRelations()), $directory));
    }

    public function discharge(DischargeAdmissionRequest $request, Admission $admission, DoctorDirectory $directory): JsonResponse
    {
        $admission = $this->admissionService->discharge($admission, $request->validated(), $request->user());

        return response()->json($this->attachDoctors($admission->load($this->showRelations()), $directory));
    }

    public function cancel(CancelAdmissionRequest $request, Admission $admission, DoctorDirectory $directory): JsonResponse
    {
        $admission = $this->admissionService->cancel($admission, $request->validated(), $request->user());

        return response()->json($this->attachDoctors($admission->load($this->showRelations()), $directory));
    }
}
