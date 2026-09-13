<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE operations MODIFY scheduled_at DATETIME NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('UPDATE operations SET scheduled_at = NOW() WHERE scheduled_at IS NULL');
            DB::statement('ALTER TABLE operations MODIFY scheduled_at DATETIME NOT NULL');
        }
    }
};
