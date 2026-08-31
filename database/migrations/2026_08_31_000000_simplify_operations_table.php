<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An operation is now just: procedure + surgeon + date. Everything tied to
     * the old priority / preop-checklist / in-progress / completion / cancellation
     * workflow is removed.
     */
    public function up(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        // Native SQLite (used by the test suite) cannot drop a column that a
        // foreign key still references, so there we leave the two FK columns
        // dangling and unused; MySQL gets the clean drop.
        if (! $isSqlite) {
            Schema::table('operations', function (Blueprint $table) {
                $table->dropForeign(['operation_room_id']);
                $table->dropForeign(['prepared_by']);
                $table->dropColumn(['operation_room_id', 'prepared_by']);
            });
        }

        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn([
                'priority',
                'diagnosis',
                'expected_duration_minutes',
                'anesthesia_type',
                'requested_by_doctor_id',
                'started_at',
                'ended_at',
                'status',
                'notes',
                'consent_obtained',
                'fasting_confirmed',
                'site_marked',
                'preop_vitals_checked',
                'preop_notes',
                'prepared_at',
                'findings',
                'complications',
                'blood_loss_ml',
                'outcome',
                'report_notes',
                'cancellation_reason',
                'cancelled_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->foreignId('operation_room_id')->nullable()->after('surgeon_id')->constrained('rooms')->nullOnDelete();
            $table->string('priority')->default('scheduled')->after('procedure_id');
            $table->text('diagnosis')->nullable()->after('priority');
            $table->unsignedInteger('expected_duration_minutes')->nullable()->after('diagnosis');
            $table->string('anesthesia_type')->nullable()->after('expected_duration_minutes');
            $table->unsignedInteger('requested_by_doctor_id')->nullable()->after('anesthesia_type');
            $table->dateTime('started_at')->nullable()->after('scheduled_at');
            $table->dateTime('ended_at')->nullable()->after('started_at');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled')->after('ended_at');
            $table->text('notes')->nullable()->after('status');
            $table->boolean('consent_obtained')->default(false)->after('notes');
            $table->boolean('fasting_confirmed')->default(false)->after('consent_obtained');
            $table->boolean('site_marked')->default(false)->after('fasting_confirmed');
            $table->boolean('preop_vitals_checked')->default(false)->after('site_marked');
            $table->text('preop_notes')->nullable()->after('preop_vitals_checked');
            $table->dateTime('prepared_at')->nullable()->after('preop_notes');
            $table->foreignId('prepared_by')->nullable()->after('prepared_at')->constrained('users')->nullOnDelete();
            $table->text('findings')->nullable()->after('prepared_by');
            $table->text('complications')->nullable()->after('findings');
            $table->unsignedInteger('blood_loss_ml')->nullable()->after('complications');
            $table->string('outcome')->nullable()->after('blood_loss_ml');
            $table->text('report_notes')->nullable()->after('outcome');
            $table->text('cancellation_reason')->nullable()->after('report_notes');
            $table->dateTime('cancelled_at')->nullable()->after('cancellation_reason');
        });
    }
};
