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
        // admission_number now resets to 1 each day, so it can no longer be globally unique.
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropUnique(['admission_number']);
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->index(['admission_date', 'admission_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropIndex(['admission_date', 'admission_number']);
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->unique('admission_number');
        });
    }
};
