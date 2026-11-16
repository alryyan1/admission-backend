<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Server-to-server bridge to the Jawda Medical clinic system's public API.
 * Used to look up real patient/doctor records for the inpatient
 * search-and-import workflow via unauthenticated read-only endpoints.
 */
class JawdaMedicalClient
{
    private readonly string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.jawda_medical.base_url'), '/');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchPatients(string $search): array
    {
        $response = Http::acceptJson()
            ->get("{$this->baseUrl}/public/patients-search", [
                'search' => $search,
                'per_page' => 15,
            ])
            ->throw();

        return $response->json('data', []);
    }

    /**
     * Register a matching patient record in Jawda Medical for a patient
     * created in the admission system, so the two systems share one
     * identity (jawda_patient_id). Jawda Medical flags the created record
     * with from_admission so it can be told apart from a walk-in.
     *
     * @param  array<string, mixed>  $patient
     * @return array<string, mixed>
     */
    public function createPatient(array $patient): array
    {
        $response = Http::acceptJson()
            ->timeout(5)
            ->post("{$this->baseUrl}/public/admission-patients", $patient)
            ->throw();

        return $response->json('data', []);
    }
}
