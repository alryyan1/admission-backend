<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportJawdaPatientRequest;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Patient;
use App\Services\AdmissionService;
use App\Services\JawdaMedicalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PatientController extends Controller
{
    /** @var list<string> */
    private const RELATIONS = ['insuranceCompany', 'company', 'guardianRelationship', 'admittingDoctor', 'referredByDoctor'];

    public function __construct(private readonly AdmissionService $admissionService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Patient::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        return response()->json(
            $query->with(self::RELATIONS)->latest()->paginate($request->integer('per_page', 15))
        );
    }

    public function show(Patient $patient): JsonResponse
    {
        return response()->json($patient->load(self::RELATIONS));
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = Patient::create([...$request->validated(), 'is_local_only' => true]);

        return response()->json($patient->load(self::RELATIONS), Response::HTTP_CREATED);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $patient->update($request->validated());

        return response()->json($patient->load(self::RELATIONS));
    }

    /**
     * Search patients directly in Jawda Medical (not yet cached locally).
     */
    public function searchJawda(Request $request, JawdaMedicalClient $client): JsonResponse
    {
        $request->validate(['search' => ['required', 'string', 'min:2']]);

        return response()->json([
            'data' => $client->searchPatients($request->string('search')->toString()),
        ]);
    }

    /**
     * Import (or refresh) a patient snapshot from Jawda Medical into the
     * local cache, keyed by jawda_patient_id.
     */
    public function importJawda(ImportJawdaPatientRequest $request): JsonResponse
    {
        $data = $request->validated();

        $patient = Patient::updateOrCreate(
            ['jawda_patient_id' => $data['jawda_patient_id']],
            [...$data, 'is_local_only' => false]
        );

        return response()->json($patient);
    }

    /**
     * Manually (re)try registering a local-only patient in Jawda Medical
     * under their latest admission, e.g. after the automatic attempt at
     * admission time failed (the clinic shift was closed, a network error,
     * etc). Unlike the automatic best-effort attempt, this reports failure
     * back to the caller.
     */
    public function linkJawda(Patient $patient): JsonResponse
    {
        if ($patient->jawda_patient_id !== null) {
            return response()->json($patient->load(self::RELATIONS));
        }

        $admission = $patient->admissions()->latest()->first();

        if ($admission === null) {
            return response()->json([
                'message' => 'لا يوجد تنويم مرتبط بهذا المريض لربطه بنظام جوده الطبي.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->admissionService->registerInJawdaMedical($admission);
        } catch (\Throwable $e) {
            Log::warning('Failed to register patient in Jawda Medical', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'فشل إنشاء المريض في نظام جوده الطبي. حاول مرة أخرى لاحقاً.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json($patient->refresh()->load(self::RELATIONS));
    }
}
