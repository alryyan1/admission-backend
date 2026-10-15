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
        Schema::table('operations', function (Blueprint $table) {
            $table->timestamp('entitlements_approved_at')->nullable()->after('payment_method_id');
            $table->foreignId('entitlements_approved_by')->nullable()->after('entitlements_approved_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entitlements_approved_by');
            $table->dropColumn('entitlements_approved_at');
        });
    }
};
