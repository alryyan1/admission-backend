<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Daily database backup at 02:00
        $schedule->command('backup:run --only-db')
            ->daily()
            ->at('02:00')
            ->appendOutputTo(storage_path('logs/backup.log'));

        // Clean up old backups after each backup run
        $schedule->command('backup:clean')
            ->daily()
            ->at('02:30')
            ->appendOutputTo(storage_path('logs/backup.log'));
        $schedule->command('whatsapp:send-admission-reminders')->daily()->at('08:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
