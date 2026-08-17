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
     * @return array<int, array<string, mixed>>
     */
    public function allDoctors(): array
    {
        $response = Http::acceptJson()
            ->get("{$this->baseUrl}/all-doctors")
            ->throw();

        return $response->json('data', []);
    }
}
