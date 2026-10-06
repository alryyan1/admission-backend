<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the stay-type column (inpatient / short_stay, always set to
     * "inpatient") with the admission entry type, plus the name of the
     * hospital a patient was transferred from.
     */
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->string('entry_type')->nullable()->after('admission_number');
            $table->string('referring_hospital_name')->nullable()->after('entry_type');
            $table->dropColumn('admission_type');
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->string('admission_type')->nullable()->after('admission_number');
            $table->dropColumn(['entry_type', 'referring_hospital_name']);
        });
    }
};
