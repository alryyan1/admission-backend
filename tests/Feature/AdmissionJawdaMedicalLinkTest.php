<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdmissionJawdaMedicalLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_admitting_an_unlinked_patient_registers_them_in_jawda_medical_with_the_admission_id(): void
    {
        Http::fake([
            '*/public/admission-patients' => Http::response(['data' => ['id' => 55]], 201),
        ]);

        $user = User::factory()->create();
        $patient = Patient::factory()->create(['jawda_patient_id' => null, 'name' => 'Ahmed Ali']);
        $bed = Bed::factory()->create(['status' => 'available']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
            'diagnosis' => 'حمى',
        ]);

        $response->assertCreated();

        Http::assertSent(function ($request) use ($response) {
            return str_contains($request->url(), '/public/admission-patients')
                && $request['name'] === 'Ahmed Ali'
                && $request['admission_id'] === $response->json('id');
        });

        $this->assertSame(55, $patient->fresh()->jawda_patient_id);
        $this->assertFalse((bool) $patient->fresh()->is_local_only);
    }

    public function test_admitting_an_already_linked_patient_does_not_call_jawda_medical_again(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $patient = Patient::factory()->create(['jawda_patient_id' => 55]);
        $bed = Bed::factory()->create(['status' => 'available']);

        $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
            'diagnosis' => 'حمى',
        ])->assertCreated();

        Http::assertNothingSent();
    }
}
