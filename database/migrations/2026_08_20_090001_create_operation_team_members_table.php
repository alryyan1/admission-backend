<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('doctor_id')->nullable();
            $table->string('name')->nullable();
            $table->string('role');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_team_members');
    }
};
