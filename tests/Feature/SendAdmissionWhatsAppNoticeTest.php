<?php

namespace Tests\Feature;

use App\Jobs\SendAdmissionWhatsAppNotice;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\Room;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendAdmissionWhatsAppNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admitting_a_patient_dispatches_a_whatsapp_notice(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $patient = Patient::factory()->create(['phone' => '01012345678']);
        $bed = Bed::factory()->create(['status' => 'available']);

        $this->actingAs($user, 'sanctum')->postJson('/api/admissions', [
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
        ])->assertCreated();

        Bus::assertDispatched(SendAdmissionWhatsAppNotice::class, fn ($job) => $job->admission->patient_id === $patient->id);
    }

    public function test_it_sends_the_configured_template_with_admission_details(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.admission_template.name' => 'admission_created',
            'services.whatsapp.admission_template.language' => 'ar',
        ]);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $room = Room::factory()->create(['room_number' => '5']);
        $bed = Bed::factory()->create(['room_id' => $room->id, 'bed_number' => '2', 'status' => 'available']);
        $patient = Patient::factory()->create(['name' => 'Ahmed Ali', 'phone' => '01012345678']);
        $admission = Admission::factory()->create(['patient_id' => $patient->id, 'bed_id' => $bed->id]);

        (new SendAdmissionWhatsAppNotice($admission))->handle(app(WhatsAppService::class));

        Http::assertSent(function ($request) {
            $params = $request['template']['components'][0]['parameters'];

            return $request['to'] === '201012345678'
                && $request['template']['name'] === 'admission_created'
                && $params[0]['text'] === 'Ahmed Ali'
                && $params[1]['text'] === '5 / 2';
        });
    }

    public function test_it_does_nothing_when_the_patient_has_no_phone(): void
    {
        Http::fake();

        $patient = Patient::factory()->create(['phone' => null]);
        $bed = Bed::factory()->create(['status' => 'available']);
        $admission = Admission::factory()->create(['patient_id' => $patient->id, 'bed_id' => $bed->id]);

        (new SendAdmissionWhatsAppNotice($admission))->handle(app(WhatsAppService::class));

        Http::assertNothingSent();
    }
}
