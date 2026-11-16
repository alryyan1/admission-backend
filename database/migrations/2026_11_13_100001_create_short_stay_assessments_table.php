<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_stay_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('blood_pressure', 15)->nullable();
            $table->unsignedTinyInteger('oxygen_saturation')->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('respiration_rate')->nullable();
            $table->text('pmh')->nullable();
            $table->text('chief_complaint')->nullable();
            $table->text('plan')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_stay_assessments');
    }
};
