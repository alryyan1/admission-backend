<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionEntryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function admitPayload(array $extra = []): array
    {
        return [
            'patient_id' => Patient::factory()->create()->id,
            'bed_id' => Bed::factory()->create(['status' => 'available'])->id,
            ...$extra,
        ];
    }

    public function test_each_entry_type_can_be_recorded_on_admission(): void
    {
        $user = User::factory()->create();

        foreach (array_keys(Admission::ENTRY_TYPES) as $entryType) {
            $payload = $this->admitPayload(['entry_type' => $entryType]);
            if ($entryType === Admission::ENTRY_TYPE_HOSPITAL_TRANSFER) {
                $payload['referring_hospital_name'] = 'مستشفى الأمل';
            }

            $this->actingAs($user, 'sanctum')
                ->postJson('/api/admissions', $payload)
                ->assertCreated()
                ->assertJsonPath('entry_type', $entryType);
        }

        $this->assertDatabaseCount('admissions', count(Admission::ENTRY_TYPES));
    }

    public function test_entry_type_rejects_a_value_outside_the_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admissions', $this->admitPayload(['entry_type' => 'admission_type_inpatient']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entry_type']);
    }

    public function test_hospital_transfer_requires_the_referring_hospital_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admissions', $this->admitPayload(['entry_type' => 'hospital_transfer']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['referring_hospital_name']);

        $this->assertDatabaseCount('admissions', 0);
    }

    public function test_hospital_transfer_stores_the_referring_hospital_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admissions', $this->admitPayload([
                'entry_type' => 'hospital_transfer',
                'referring_hospital_name' => '  مستشفى الأمل  ',
            ]))
            ->assertCreated()
            ->assertJsonPath('referring_hospital_name', 'مستشفى الأمل');
    }

    public function test_referring_hospital_name_is_discarded_unless_the_entry_is_a_transfer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admissions', $this->admitPayload([
                'entry_type' => 'emergency',
                'referring_hospital_name' => 'مستشفى الأمل',
            ]))
            ->assertCreated()
            ->assertJsonPath('referring_hospital_name', null);
    }

    public function test_statistics_group_admissions_by_entry_type_with_unspecified_for_missing(): void
    {
        $user = User::factory()->create();
        Admission::factory()->create(['admission_date' => now(), 'entry_type' => 'emergency']);
        Admission::factory()->create(['admission_date' => now(), 'entry_type' => 'emergency']);
        Admission::factory()->create(['admission_date' => now(), 'entry_type' => 'scheduled']);
        Admission::factory()->create(['admission_date' => now(), 'entry_type' => null]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/statistics/admissions');

        $response->assertOk();
        $response->assertJsonPath('type_counts.emergency', 2);
        $response->assertJsonPath('type_counts.scheduled', 1);
        $response->assertJsonPath('type_counts.unspecified', 1);
    }

    public function test_admission_summary_pdf_renders_for_a_hospital_transfer(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create([
            'entry_type' => 'hospital_transfer',
            'referring_hospital_name' => 'مستشفى الأمل',
        ]);

        $response = $this->actingAs($user, 'sanctum')->get("/api/admissions/{$admission->id}/summary.pdf");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
