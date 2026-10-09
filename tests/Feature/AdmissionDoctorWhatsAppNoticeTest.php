<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Doctor;
use App\Models\FacilitySetting;
use App\Models\Patient;
use App\Models\Room;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdmissionDoctorWhatsAppNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admitting_a_patient_sends_the_template_directly_to_the_referring_doctor(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.doctor_admission_template.name' => 'admission_doctor_notice',
            'services.whatsapp.doctor_admission_template.language' => 'ar',
            'services.whatsapp.default_country_code' => '20',
        ]);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        FacilitySetting::current()->update(['name' => 'مستشفى د. السر ميرغني']);

        $user = User::factory()->create();
        $doctor = Doctor::factory()->create(['name' => 'د. الريان', 'phone' => '01098765432']);
        $admittingDoctor = Doctor::factory()->create(['phone' => '01011112222']);
        $ward = Ward::factory()->create(['name' => 'الباطنية']);
        $room = Room::factory()->create(['ward_id' => $ward->id, 'room_number' => '1']);
        $bed = Bed::factory()->create(['room_id' => $room->id, 'bed_number' => '1', 'status' => 'available']);
        $patient = Patient::factory()->create([
            'name' => 'علي أحمد',
            'referred_by_doctor_id' => $doctor->id,
            'admitting_doctor_id' => $admittingDoctor->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('whatsapp_doctor_notice.sent', true)
            ->assertJsonPath('whatsapp_doctor_notice.message', 'تم إرسال إشعار واتساب للطبيب المحوِّل بنجاح.');

        Http::assertSent(function ($request) use ($ward) {
            $params = $request['template']['components'][0]['parameters'];

            return $request['to'] === '201098765432'
                && $request['template']['name'] === 'admission_doctor_notice'
                && $params[0]['text'] === 'مستشفى د. السر ميرغني'
                && $params[1]['text'] === 'د. الريان'
                && $params[2]['text'] === 'علي أحمد'
                && $params[4]['text'] === $ward->name
                && $params[5]['text'] === '1 / 1';
        });
    }

    public function test_it_does_not_send_when_the_patient_has_no_referring_doctor(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $bed = Bed::factory()->create(['status' => 'available']);
        $patient = Patient::factory()->create(['referred_by_doctor_id' => null]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
        ]);

        $response->assertCreated()->assertJsonPath('whatsapp_doctor_notice.sent', false);

        Http::assertNothingSent();
    }

    public function test_registering_a_patient_without_a_bed_does_not_send_a_notice(): void
    {
        config(['services.whatsapp.phone_number_id' => '1234567890', 'services.whatsapp.access_token' => 'test-token']);

        Http::fake();

        $user = User::factory()->create();
        $doctor = Doctor::factory()->create(['phone' => '01098765432']);
        $patient = Patient::factory()->create(['referred_by_doctor_id' => $doctor->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admissions/register', [
            'patient_id' => $patient->id,
        ]);

        $response->assertCreated();
        $this->assertArrayNotHasKey('whatsapp_doctor_notice', $response->json());

        Http::assertNothingSent();
    }

    public function test_assigning_a_bed_to_a_registered_admission_sends_the_notice(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.doctor_admission_template.name' => 'admission_doctor_notice',
            'services.whatsapp.doctor_admission_template.language' => 'ar',
            'services.whatsapp.default_country_code' => '20',
        ]);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $user = User::factory()->create();
        $doctor = Doctor::factory()->create(['name' => 'د. الريان', 'phone' => '01098765432']);
        $room = Room::factory()->create(['room_number' => '7']);
        $bed = Bed::factory()->create(['room_id' => $room->id, 'bed_number' => '3', 'status' => 'available']);
        $patient = Patient::factory()->create(['name' => 'فاطمة علي', 'referred_by_doctor_id' => $doctor->id]);
        $admission = Admission::factory()->withoutBed()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($user, 'sanctum')->patchJson("/api/admissions/{$admission->id}/bed", [
            'bed_id' => $bed->id,
        ]);

        $response->assertOk()->assertJsonPath('whatsapp_doctor_notice.sent', true);

        Http::assertSent(function ($request) {
            $params = $request['template']['components'][0]['parameters'];

            return $request['to'] === '201098765432'
                && $params[2]['text'] === 'فاطمة علي'
                && $params[5]['text'] === '7 / 3';
        });
    }

    public function test_it_ignores_the_admitting_doctor_and_only_targets_the_referring_doctor(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.default_country_code' => '20',
        ]);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $user = User::factory()->create();
        $referringDoctor = Doctor::factory()->create(['phone' => '01033334444']);
        $admittingDoctor = Doctor::factory()->create(['phone' => '01099998888']);
        $bed = Bed::factory()->create(['status' => 'available']);
        $patient = Patient::factory()->create([
            'referred_by_doctor_id' => $referringDoctor->id,
            'admitting_doctor_id' => $admittingDoctor->id,
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
        ])->assertCreated();

        Http::assertSent(fn ($request) => $request['to'] === '201033334444');
        Http::assertNotSent(fn ($request) => $request['to'] === '201099998888');
    }
}
