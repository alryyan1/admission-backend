<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportJawdaPatientRequest;
use App\Http\Requests\StorePatientRequest;
use App\Models\Patient;
use App\Services\JawdaMedicalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PatientController extends Controller
{
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

        return response()->json($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function show(Patient $patient): JsonResponse
    {
        return response()->json($patient);
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = Patient::create([...$request->validated(), 'is_local_only' => true]);

        return response()->json($patient, Response::HTTP_CREATED);
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
}
