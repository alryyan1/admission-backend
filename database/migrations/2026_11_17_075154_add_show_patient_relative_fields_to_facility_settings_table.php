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
            $table->boolean('show_patient_relative_fields')->default(false)->after('notify_referring_doctor_whatsapp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_settings', function (Blueprint $table) {
            $table->dropColumn(['show_patient_relative_fields']);
        });
    }
};
