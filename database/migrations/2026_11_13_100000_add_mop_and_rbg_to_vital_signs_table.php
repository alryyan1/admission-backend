<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->string('mop', 50)->nullable()->after('oxygen_saturation');
            $table->unsignedSmallInteger('rbg')->nullable()->after('mop');
        });
    }

    public function down(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropColumn(['mop', 'rbg']);
        });
    }
};
