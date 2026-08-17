<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Services\JawdaMedicalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index(Request $request, JawdaMedicalClient $client): JsonResponse
    {
        if (Doctor::query()->doesntExist()) {
            $this->syncFromJawda($client);
        }

        $search = trim((string) $request->query('search', ''));

        $doctors = Doctor::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        return response()->json($doctors);
    }

    /**
     * Pull the current doctor list from Jawda Medical and upsert it into the
     * local cache, keyed by jawda_doctor_id.
     */
    public function syncJawda(JawdaMedicalClient $client): JsonResponse
    {
        $this->syncFromJawda($client);

        return response()->json(Doctor::orderBy('name')->get());
    }

    private function syncFromJawda(JawdaMedicalClient $client): void
    {
        foreach ($client->allDoctors() as $doctor) {
            Doctor::updateOrCreate(
                ['jawda_doctor_id' => $doctor['id']],
                [
                    'name' => $doctor['name'],
                    'specialist' => $doctor['specialist_name'] ?? null,
                ]
            );
        }
    }
}
