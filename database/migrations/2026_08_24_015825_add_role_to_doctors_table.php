<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            // Same value set as operations_team_members.role, so an operation
            // team-member row can filter the doctor picker by matching role.
            $table->string('role')->default('surgeon')->after('specialist');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
