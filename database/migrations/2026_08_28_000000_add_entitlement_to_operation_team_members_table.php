<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_team_members', function (Blueprint $table) {
            $table->decimal('entitlement_amount', 10, 2)->nullable()->after('notes');
            $table->foreignId('payment_method_id')->nullable()->after('entitlement_amount')->constrained('payment_methods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('operation_team_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('entitlement_amount');
        });
    }
};
