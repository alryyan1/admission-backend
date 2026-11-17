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
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('insurance_card_number')->constrained()->nullOnDelete();
            $table->foreignId('guardian_relationship_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guardian_relationship_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
