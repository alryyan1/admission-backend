<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('bed_id')->constrained()->restrictOnDelete();
            $table->foreignId('admitting_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('admitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('discharged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('admission_date');
            $table->dateTime('discharge_date')->nullable();
            $table->unsignedSmallInteger('admission_duration_hours')->nullable();
            $table->enum('status', ['admitted', 'discharged'])->default('admitted');
            $table->string('diagnosis')->nullable();
            $table->text('admission_notes')->nullable();
            $table->text('discharge_summary')->nullable();
            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
