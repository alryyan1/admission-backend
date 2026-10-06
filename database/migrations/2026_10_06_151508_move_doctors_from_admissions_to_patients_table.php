<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admitting doctor and the referring doctor belong to the patient, not to each admission.
     *
     * Existing admission doctor values are test data and are not carried over. SQLite (used by the
     * test suite) cannot drop a column that a foreign key references, so there the admission columns are
     * left unused; MySQL gets the clean drop.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('admitting_doctor_id')->nullable()->after('insurance_card_number')
                ->constrained('doctors')->nullOnDelete();
            $table->foreignId('referred_by_doctor_id')->nullable()->after('admitting_doctor_id')
                ->constrained('doctors')->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropForeign(['admitting_doctor_id']);
            $table->dropForeign(['referred_by_doctor_id']);
            $table->dropColumn(['admitting_doctor_id', 'referred_by_doctor_id']);
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('admissions', function (Blueprint $table) {
                $table->foreignId('admitting_doctor_id')->nullable()->after('bed_id')
                    ->constrained('doctors')->nullOnDelete();
                $table->foreignId('referred_by_doctor_id')->nullable()->after('admitting_doctor_id')
                    ->constrained('doctors')->nullOnDelete();
            });
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['admitting_doctor_id']);
            $table->dropForeign(['referred_by_doctor_id']);
            $table->dropColumn(['admitting_doctor_id', 'referred_by_doctor_id']);
        });
    }
};
