<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The doctor who referred the patient for this admission (external or
     * internal). Distinct from admitting_doctor_id, which is the attending
     * physician responsible for the stay.
     */
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->foreignId('referred_by_doctor_id')
                ->nullable()
                ->after('admitting_doctor_id')
                ->constrained('doctors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_doctor_id');
        });
    }
};
