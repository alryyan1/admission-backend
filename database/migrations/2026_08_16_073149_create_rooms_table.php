<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->string('room_number');
            $table->enum('room_type', ['normal', 'vip'])->default('normal');
            $table->unsignedInteger('capacity')->default(1);
            $table->decimal('price_per_day', 10, 2)->nullable();
            $table->boolean('is_short_stay')->default(false);
            $table->decimal('price_12_hours', 10, 2)->nullable();
            $table->decimal('price_24_hours', 10, 2)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['ward_id', 'room_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
