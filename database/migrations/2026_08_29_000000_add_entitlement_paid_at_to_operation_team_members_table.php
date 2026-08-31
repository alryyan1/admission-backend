<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_team_members', function (Blueprint $table) {
            $table->date('entitlement_paid_at')->nullable()->after('payment_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('operation_team_members', function (Blueprint $table) {
            $table->dropColumn('entitlement_paid_at');
        });
    }
};
