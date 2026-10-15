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
        Schema::table('admission_deposits', function (Blueprint $table) {
            $table->foreignId('requested_service_id')->nullable()->after('admission_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_service_id');
        });
    }
};
