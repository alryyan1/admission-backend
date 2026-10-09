<?php

namespace App\Jobs;

use App\Models\Admission;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAdmissionReminderWhatsAppNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Admission $admission) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $this->admission->loadMissing(['patient.referredByDoctor', 'bed.room']);

        $phone = $this->admission->patient?->referredByDoctor?->phone;

        if (blank($phone) || ! $whatsApp->isConfigured()) {
            return;
        }

        $room = $this->admission->bed
            ? $this->admission->bed->room->room_number.' / '.$this->admission->bed->bed_number
            : '-';

        try {
            $whatsApp->sendTemplate(
                toPhone: $phone,
                templateName: (string) config('services.whatsapp.reminder_template.name'),
                languageCode: (string) config('services.whatsapp.reminder_template.language'),
                bodyParameters: [
                    ['type' => 'text', 'text' => $this->admission->patient->name],
                    ['type' => 'text', 'text' => $room],
                    ['type' => 'text', 'text' => $this->admission->admission_date->format('d-m-Y')],
                    ['type' => 'text', 'text' => $this->admission->admission_date->format('G:i')],
                ],
            );
        } catch (\Throwable $exception) {
            Log::error('Failed to send WhatsApp admission reminder.', [
                'admission_id' => $this->admission->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
