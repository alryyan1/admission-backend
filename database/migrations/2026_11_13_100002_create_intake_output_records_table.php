<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_output_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at');
            $table->decimal('feeding', 7, 2)->nullable();
            $table->decimal('iv_fluid', 7, 2)->nullable();
            $table->decimal('maintenance', 7, 2)->nullable();
            $table->decimal('urine_output', 7, 2)->nullable();
            $table->decimal('insensible_losses', 7, 2)->nullable();
            $table->decimal('total_intake', 7, 2)->nullable();
            $table->decimal('total_output', 7, 2)->nullable();
            $table->decimal('balance', 7, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_output_records');
    }
};
