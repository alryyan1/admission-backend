<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntakeOutputRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_nurse_can_record_an_intake_output_entry(): void
    {
        $nurse = User::factory()->role('nurse')->create();
        $admission = Admission::factory()->create();

        $response = $this->actingAs($nurse, 'sanctum')->postJson("/api/admissions/{$admission->id}/intake-output", [
            'feeding' => 100,
            'iv_fluid' => 500,
            'total_intake' => 600,
            'total_output' => 350,
            'balance' => 250,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('intake_output_records', [
            'admission_id' => $admission->id,
            'recorded_by' => $nurse->id,
            'total_intake' => 600,
            'total_output' => 350,
            'balance' => 250,
        ]);
    }

    public function test_index_returns_entries_newest_first(): void
    {
        $nurse = User::factory()->role('nurse')->create();
        $admission = Admission::factory()->create();
        $admission->intakeOutputRecords()->create(['recorded_at' => now()->subHour(), 'feeding' => 50]);
        $admission->intakeOutputRecords()->create(['recorded_at' => now(), 'feeding' => 75]);

        $response = $this->actingAs($nurse, 'sanctum')->getJson("/api/admissions/{$admission->id}/intake-output");

        $response->assertOk();
        $this->assertSame(75, (int) $response->json('0.feeding'));
    }

    public function test_cannot_record_an_entry_for_a_discharged_admission(): void
    {
        $nurse = User::factory()->role('nurse')->create();
        $admission = Admission::factory()->create(['status' => 'discharged', 'discharge_date' => now()]);

        $response = $this->actingAs($nurse, 'sanctum')->postJson("/api/admissions/{$admission->id}/intake-output", [
            'feeding' => 100,
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('intake_output_records', ['admission_id' => $admission->id]);
    }
}
