<?php

namespace Tests\Feature;

use App\Jobs\SendAdmissionReminderWhatsAppNotice;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Room;
use App\Services\WhatsAppService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendAdmissionReminderWhatsAppNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_reminders_are_scheduled_to_run_daily_at_eight_am(): void
    {
        $events = app(Schedule::class)->events();

        $event = collect($events)->first(
            fn ($event) => str_contains($event->command ?? '', 'whatsapp:send-admission-reminders'),
        );

        $this->assertNotNull($event, 'The whatsapp:send-admission-reminders command is not scheduled.');
        $this->assertSame('0 8 * * *', $event->expression);
    }

    public function test_the_command_dispatches_a_reminder_job_for_every_admitted_patient(): void
    {
        Bus::fake();

        $admitted = Admission::factory()->create(['status' => 'admitted']);
        Admission::factory()->create(['status' => 'discharged']);
        Admission::factory()->create(['status' => 'cancelled']);

        $this->artisan('whatsapp:send-admission-reminders')->assertSuccessful();

        Bus::assertDispatched(SendAdmissionReminderWhatsAppNotice::class, fn ($job) => $job->admission->is($admitted));
        Bus::assertDispatchedTimes(SendAdmissionReminderWhatsAppNotice::class, 1);
    }

    public function test_it_sends_the_configured_template_to_the_admitting_doctor(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.reminder_template.name' => 'admission_reminder',
            'services.whatsapp.reminder_template.language' => 'ar',
        ]);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $doctor = Doctor::factory()->create(['phone' => '01098765432']);
        $room = Room::factory()->create(['room_number' => '5']);
        $bed = Bed::factory()->create(['room_id' => $room->id, 'bed_number' => '2', 'status' => 'available']);
        $patient = Patient::factory()->create(['name' => 'Ahmed Ali', 'admitting_doctor_id' => $doctor->id]);
        $admission = Admission::factory()->create([
            'patient_id' => $patient->id,
            'bed_id' => $bed->id,
            'admission_date' => '2026-06-22 04:00:00',
        ]);

        (new SendAdmissionReminderWhatsAppNotice($admission))->handle(app(WhatsAppService::class));

        Http::assertSent(function ($request) {
            $params = $request['template']['components'][0]['parameters'];

            return $request['to'] === '201098765432'
                && $request['template']['name'] === 'admission_reminder'
                && $params[0]['text'] === 'Ahmed Ali'
                && $params[1]['text'] === '5 / 2'
                && $params[2]['text'] === '22-06-2026'
                && $params[3]['text'] === '4:00';
        });
    }

    public function test_it_does_nothing_when_the_patient_has_no_admitting_doctor(): void
    {
        Http::fake();

        $patient = Patient::factory()->create(['admitting_doctor_id' => null]);
        $admission = Admission::factory()->create(['patient_id' => $patient->id]);

        (new SendAdmissionReminderWhatsAppNotice($admission))->handle(app(WhatsAppService::class));

        Http::assertNothingSent();
    }
}
