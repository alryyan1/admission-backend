<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_backup_is_scheduled_to_run_automatically(): void
    {
        $events = app(Schedule::class)->events();

        $backupEvent = collect($events)->first(
            fn ($event) => str_contains($event->command ?? '', 'backup:run'),
        );

        $this->assertNotNull($backupEvent, 'The backup:run command is not scheduled.');
        $this->assertSame('0 2 * * *', $backupEvent->expression);
    }

    public function test_admin_can_trigger_a_database_backup(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run', ['--only-db' => true])
            ->andReturn(0);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/backups');

        $response->assertOk()->assertJsonPath('message', 'تم إنشاء النسخة الاحتياطية بنجاح');
    }

    public function test_non_admin_cannot_trigger_a_database_backup(): void
    {
        $user = User::factory()->create(['role' => 'nurse']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/backups');

        $response->assertForbidden();
    }
}
