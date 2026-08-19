<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->string('operation_type')->nullable()->after('procedure_name');

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
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropColumn([
                'operation_type',
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
            ]);
        });
    }
};
