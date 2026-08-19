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
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_address')->nullable();

            $table->string('blood_type')->nullable();
            $table->text('allergies')->nullable();
            $table->text('chronic_diseases')->nullable();
            $table->text('current_medications')->nullable();
            $table->text('past_surgeries')->nullable();
            $table->text('medical_history')->nullable();
            $table->text('medical_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
                'emergency_contact_address',
                'blood_type',
                'allergies',
                'chronic_diseases',
                'current_medications',
                'past_surgeries',
                'medical_history',
                'medical_notes',
            ]);
        });
    }
};
