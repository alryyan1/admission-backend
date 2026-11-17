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
        Schema::dropIfExists('treatment_doses');
        Schema::dropIfExists('doctor_orders');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('doctor_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_text');
            $table->string('frequency')->nullable();
            $table->string('route')->nullable();
            $table->enum('status', ['active', 'discontinued'])->default('active');
            $table->timestamps();
        });

        Schema::create('treatment_doses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('administered_at');
            $table->enum('status', ['given', 'missed', 'refused'])->default('given');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
