<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionCompanionContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_endpoint_returns_the_patients_emergency_contact(): void
    {
        $patient = Patient::factory()->create([
            'emergency_contact_name' => 'سارة أحمد',
            'emergency_contact_relationship' => 'زوجة',
            'emergency_contact_phone' => '0912345678',
        ]);
        $admission = Admission::factory()->create(['patient_id' => $patient->id]);

        $response = $this->getJson("/api/public/admissions/{$admission->id}/companion-contact");

        $response->assertOk()->assertExactJson([
            'name' => 'سارة أحمد',
            'relationship' => 'زوجة',
            'phone' => '0912345678',
        ]);
    }

    public function test_it_does_not_require_authentication(): void
    {
        $admission = Admission::factory()->create();

        $this->getJson("/api/public/admissions/{$admission->id}/companion-contact")
            ->assertOk();
    }
}
