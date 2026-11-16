<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortStayAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_create_then_update_the_assessment(): void
    {
        $doctor = User::factory()->role('doctor')->create();
        $admission = Admission::factory()->create();

        $firstResponse = $this->actingAs($doctor, 'sanctum')->postJson("/api/admissions/{$admission->id}/short-stay-assessment", [
            'pmh' => 'لا يوجد',
            'chief_complaint' => 'حمى',
            'plan' => 'مراقبة',
        ]);

        $firstResponse->assertOk();
        $this->assertDatabaseCount('short_stay_assessments', 1);
        $this->assertDatabaseHas('short_stay_assessments', [
            'admission_id' => $admission->id,
            'assessed_by' => $doctor->id,
            'chief_complaint' => 'حمى',
        ]);

        $secondResponse = $this->actingAs($doctor, 'sanctum')->postJson("/api/admissions/{$admission->id}/short-stay-assessment", [
            'pmh' => 'لا يوجد',
            'chief_complaint' => 'حمى وألم بالبطن',
            'plan' => 'مراقبة وسوائل وريدية',
        ]);

        $secondResponse->assertOk();
        $this->assertDatabaseCount('short_stay_assessments', 1);
        $this->assertDatabaseHas('short_stay_assessments', [
            'admission_id' => $admission->id,
            'chief_complaint' => 'حمى وألم بالبطن',
        ]);
    }

    public function test_show_returns_null_when_not_yet_assessed(): void
    {
        $doctor = User::factory()->role('doctor')->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($doctor, 'sanctum')->getJson("/api/admissions/{$admission->id}/short-stay-assessment");

        // A literal JSON "null" body (not "{}") so the frontend sees a real
        // null rather than a truthy empty object. Laravel's TestResponse::json()
        // treats a bare "null" body as "invalid JSON" and fails, so assert on
        // the raw content instead.
        $response->assertOk();
        $this->assertSame('null', $response->getContent());
    }
}
