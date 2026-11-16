<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL requires doctor_id to match doctors.id's BIGINT UNSIGNED type before
        // it can be used in a foreign key; other drivers (e.g. sqlite in tests) don't
        // enforce column types this strictly, so the raw ALTER is MySQL-only.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE operation_team_members MODIFY doctor_id BIGINT UNSIGNED NULL');
        }

        Schema::table('operation_team_members', function (Blueprint $table) {
            $table->foreign('doctor_id')->references('id')->on('doctors')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_team_members', function (Blueprint $table) {
            $table->dropForeign(['doctor_id']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE operation_team_members MODIFY doctor_id INT UNSIGNED NULL');
        }
    }
};
