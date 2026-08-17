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
        Schema::table('admissions', function (Blueprint $table) {
            $table->string('admission_number')->nullable()->unique()->after('id');
            $table->string('admission_type')->nullable()->after('admission_number');
            $table->foreignId('cancelled_by')->nullable()->after('discharged_by')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('discharge_summary');
            $table->dateTime('cancelled_at')->nullable()->after('cancellation_reason');
        });

        // Status was originally a DB-level enum('admitted','discharged'). Widening an enum requires
        // doctrine/dbal (not a project dependency), so it's replaced with an application-validated
        // string column instead, adding 'cancelled' as an allowed value.
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->string('status')->default('admitted')->after('admission_duration_hours');
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->enum('status', ['admitted', 'discharged'])->default('admitted')->after('admission_duration_hours');
            $table->index(['status']);
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['admission_number', 'admission_type', 'cancellation_reason', 'cancelled_at']);
        });
    }
};
