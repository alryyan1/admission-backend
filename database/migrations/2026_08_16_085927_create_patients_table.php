<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jawda_patient_id')->nullable()->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->unsignedSmallInteger('age_year')->nullable();
            $table->unsignedSmallInteger('age_month')->nullable();
            $table->unsignedSmallInteger('age_day')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_local_only')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
