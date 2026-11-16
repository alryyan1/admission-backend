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
        DB::statement('ALTER TABLE operation_team_members MODIFY doctor_id BIGINT UNSIGNED NULL');

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

        DB::statement('ALTER TABLE operation_team_members MODIFY doctor_id INT UNSIGNED NULL');
    }
};
