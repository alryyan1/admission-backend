<?php

namespace Tests\Feature;

use App\Services\JawdaMedicalClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JawdaMedicalClientTest extends TestCase
{
    public function test_search_patients_calls_the_public_endpoint_without_authentication(): void
    {
        Http::fake([
            '*/public/patients-search*' => Http::response([
                'data' => [
                    ['id' => 1, 'name' => 'Ahmed', 'phone' => '0991961111'],
                ],
            ], 200),
        ]);

        $client = new JawdaMedicalClient('http://jawda-medical.test/api');
        $results = $client->searchPatients('Ahmed');

        $this->assertSame([['id' => 1, 'name' => 'Ahmed', 'phone' => '0991961111']], $results);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://jawda-medical.test/api/public/patients-search?search=Ahmed&per_page=15'
                && ! $request->hasHeader('Authorization');
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/login'));
    }

    public function test_all_doctors_calls_the_public_endpoint_without_authentication(): void
    {
        Http::fake([
            '*/all-doctors*' => Http::response([
                'data' => [
                    ['id' => 1, 'name' => 'Dr. Sara', 'specialist_name' => 'Cardiology'],
                ],
            ], 200),
        ]);

        $client = new JawdaMedicalClient('http://jawda-medical.test/api');
        $results = $client->allDoctors();

        $this->assertSame([['id' => 1, 'name' => 'Dr. Sara', 'specialist_name' => 'Cardiology']], $results);

        Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/login'));
    }
}
