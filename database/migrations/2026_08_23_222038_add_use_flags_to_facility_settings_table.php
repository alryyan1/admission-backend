<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facility_settings', function (Blueprint $table) {
            $table->boolean('use_logo')->default(true)->after('logo_path');
            $table->boolean('use_stamp')->default(true)->after('stamp_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_settings', function (Blueprint $table) {
            $table->dropColumn(['use_logo', 'use_stamp']);
        });
    }
};
