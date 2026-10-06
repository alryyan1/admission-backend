<?php

namespace Tests\Feature;

use App\Jobs\SendAdmissionWhatsAppNotice;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\ChartOpeningServiceSetting;
use App\Models\Patient;
use App\Models\RequestedService;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdmissionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_a_patient_creates_an_active_admission_without_a_bed(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admissions/register', [
            'patient_id' => $patient->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('patient_id', $patient->id)
            ->assertJsonPath('bed_id', null)
            ->assertJsonPath('status', 'admitted')
            ->assertJsonPath('admitted_by.id', $user->id);
        $this->assertMatchesRegularExpression('/^\d+$/', $response->json('admission_number'));
        $this->assertSame('available', $bed->refresh()->status);
    }

    public function test_registering_a_patient_adds_the_chart_opening_service_when_enabled(): void
    {
        $service = Service::factory()->create(['name_ar' => 'فتح ملف', 'price' => 50]);
        ChartOpeningServiceSetting::current()->update(['service_id' => $service->id, 'auto_add' => true]);

        $response = $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/admissions/register', [
            'patient_id' => Patient::factory()->create()->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('requested_services', [
            'admission_id' => $response->json('id'),
            'name' => 'فتح ملف',
            'is_auto_added' => true,
        ]);
    }

    public function test_registering_requires_an_existing_patient(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/admissions/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_id']);

        $this->actingAs($user, 'sanctum')->postJson('/api/admissions/register', ['patient_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_id']);

        $this->assertDatabaseCount('admissions', 0);
    }

    public function test_registering_is_forbidden_for_roles_outside_admission_clerk_and_admin(): void
    {
        $nurse = User::factory()->role('nurse')->create();

        $this->actingAs($nurse, 'sanctum')->postJson('/api/admissions/register', [
            'patient_id' => Patient::factory()->create()->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('admissions', 0);
    }

    public function test_assigning_a_bed_to_a_bedless_admission_occupies_the_bed(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->withoutBed()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $response = $this->actingAs($user, 'sanctum')->patchJson("/api/admissions/{$admission->id}/bed", [
            'bed_id' => $bed->id,
        ]);

        $response->assertOk()->assertJsonPath('bed_id', $bed->id);
        $this->assertSame('occupied', $bed->refresh()->status);
    }

    public function test_assigning_a_bed_queues_the_admission_whatsapp_notice(): void
    {
        Queue::fake();
        $admission = Admission::factory()->withoutBed()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $this->actingAs(User::factory()->create(), 'sanctum')->patchJson("/api/admissions/{$admission->id}/bed", [
            'bed_id' => $bed->id,
        ])->assertOk();

        Queue::assertPushed(SendAdmissionWhatsAppNotice::class);
    }

    public function test_assigning_an_unavailable_bed_is_rejected_and_leaves_the_admission_bedless(): void
    {
        $admission = Admission::factory()->withoutBed()->create();
        $occupiedBed = Bed::factory()->create(['status' => 'occupied']);

        $this->actingAs(User::factory()->create(), 'sanctum')->patchJson("/api/admissions/{$admission->id}/bed", [
            'bed_id' => $occupiedBed->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['bed_id']);

        $this->assertNull($admission->refresh()->bed_id);
        $this->assertSame('occupied', $occupiedBed->refresh()->status);
    }

    public function test_assigning_a_bed_to_a_missing_bed_id_is_rejected(): void
    {
        $admission = Admission::factory()->withoutBed()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')->patchJson("/api/admissions/{$admission->id}/bed", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bed_id']);
    }

    public function test_assigning_a_bed_to_an_admission_that_already_has_one_is_rejected(): void
    {
        $currentBed = Bed::factory()->create(['status' => 'occupied']);
        $admission = Admission::factory()->create(['bed_id' => $currentBed->id]);
        $newBed = Bed::factory()->create(['status' => 'available']);

        $this->actingAs(User::factory()->create(), 'sanctum')->patchJson("/api/admissions/{$admission->id}/bed", [
            'bed_id' => $newBed->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['bed_id']);

        $this->assertSame($currentBed->id, $admission->refresh()->bed_id);
        $this->assertSame('available', $newBed->refresh()->status);
    }

    public function test_assigning_a_bed_to_a_discharged_admission_is_rejected(): void
    {
        $admission = Admission::factory()->withoutBed()->create(['status' => 'discharged']);
        $bed = Bed::factory()->create(['status' => 'available']);

        $this->actingAs(User::factory()->create(), 'sanctum')->patchJson("/api/admissions/{$admission->id}/bed", [
            'bed_id' => $bed->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['bed_id']);

        $this->assertNull($admission->refresh()->bed_id);
    }

    public function test_assigning_a_bed_is_forbidden_for_doctors(): void
    {
        $admission = Admission::factory()->withoutBed()->create();
        $bed = Bed::factory()->create(['status' => 'available']);

        $this->actingAs(User::factory()->role('doctor')->create(), 'sanctum')->patchJson(
            "/api/admissions/{$admission->id}/bed",
            ['bed_id' => $bed->id],
        )->assertForbidden();

        $this->assertNull($admission->refresh()->bed_id);
    }

    public function test_discharging_a_bedless_admission_does_not_touch_any_bed(): void
    {
        $admission = Admission::factory()->withoutBed()->create();

        $admission->update(['status' => 'discharged', 'discharge_date' => now()]);

        $this->assertSame('discharged', $admission->refresh()->status);
        $this->assertNull($admission->bed_id);
    }

    public function test_cancelling_a_bedless_admission_does_not_touch_any_bed(): void
    {
        $admission = Admission::factory()->withoutBed()->create();

        $admission->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $this->assertSame('cancelled', $admission->refresh()->status);
        $this->assertNull($admission->bed_id);
    }

    public function test_accommodation_fee_is_rejected_for_an_admission_without_a_bed(): void
    {
        $admission = Admission::factory()->withoutBed()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/services/accommodation-fee")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bed_id']);

        $this->assertDatabaseMissing('requested_services', [
            'admission_id' => $admission->id,
            'name' => RequestedService::AccommodationFeeName,
        ]);
    }

    public function test_accommodation_fee_still_uses_the_bed_room_price_once_a_bed_is_assigned(): void
    {
        $room = Room::factory()->create(['price_per_day' => 300]);
        $bed = Bed::factory()->create(['room_id' => $room->id, 'status' => 'occupied']);
        $admission = Admission::factory()->create(['bed_id' => $bed->id]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/admissions/{$admission->id}/services/accommodation-fee")
            ->assertCreated()
            ->assertJsonPath('unit_price', '300.00');
    }

    public function test_whatsapp_notice_returns_early_without_calling_the_provider_when_there_is_no_bed(): void
    {
        $this->mock(WhatsAppService::class, function ($mock): void {
            $mock->shouldNotReceive('sendTemplate');
            $mock->shouldNotReceive('isConfigured');
        });

        $admission = Admission::factory()->withoutBed()->create([
            'patient_id' => Patient::factory()->create(['phone' => '0500000000'])->id,
        ]);

        (new SendAdmissionWhatsAppNotice($admission))->handle(app(WhatsAppService::class));

        $this->assertNull($admission->refresh()->bed_id);
    }

    public function test_releasing_a_bed_clears_it_from_the_admission_and_makes_the_bed_available(): void
    {
        $bed = Bed::factory()->create(['status' => 'occupied']);
        $admission = Admission::factory()->create(['bed_id' => $bed->id]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/admissions/{$admission->id}/bed")
            ->assertOk()
            ->assertJsonPath('bed_id', null)
            ->assertJsonPath('status', 'admitted');

        $this->assertNull($admission->refresh()->bed_id);
        $this->assertSame('admitted', $admission->status);
        $this->assertSame('available', $bed->refresh()->status);
    }

    public function test_releasing_a_bed_from_an_admission_without_one_is_rejected(): void
    {
        $admission = Admission::factory()->withoutBed()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/admissions/{$admission->id}/bed")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bed_id']);
    }

    public function test_releasing_a_bed_from_a_discharged_admission_is_rejected_and_keeps_the_bed(): void
    {
        $bed = Bed::factory()->create(['status' => 'occupied']);
        $admission = Admission::factory()->create(['bed_id' => $bed->id, 'status' => 'discharged']);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/admissions/{$admission->id}/bed")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bed_id']);

        $this->assertSame($bed->id, $admission->refresh()->bed_id);
        $this->assertSame('occupied', $bed->refresh()->status);
    }

    public function test_releasing_a_bed_is_forbidden_for_doctors(): void
    {
        $bed = Bed::factory()->create(['status' => 'occupied']);
        $admission = Admission::factory()->create(['bed_id' => $bed->id]);

        $this->actingAs(User::factory()->role('doctor')->create(), 'sanctum')
            ->deleteJson("/api/admissions/{$admission->id}/bed")
            ->assertForbidden();

        $this->assertSame($bed->id, $admission->refresh()->bed_id);
    }
}
