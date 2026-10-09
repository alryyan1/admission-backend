<?php

namespace App\Console\Commands;

use App\Jobs\SendAdmissionReminderWhatsAppNotice;
use App\Models\Admission;
use Illuminate\Console\Command;

class SendAdmissionReminders extends Command
{
    protected $signature = 'whatsapp:send-admission-reminders';

    protected $description = 'Dispatch a WhatsApp reminder to the referring doctor of every currently admitted patient';

    public function handle(): int
    {
        $admissions = Admission::query()
            ->where('status', 'admitted')
            ->with(['patient.referredByDoctor', 'bed.room'])
            ->get();

        foreach ($admissions as $admission) {
            SendAdmissionReminderWhatsAppNotice::dispatch($admission);
        }

        $this->info("Dispatched {$admissions->count()} admission reminder(s).");

        return self::SUCCESS;
    }
}
