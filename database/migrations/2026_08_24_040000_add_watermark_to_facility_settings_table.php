<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_settings', function (Blueprint $table) {
            $table->string('watermark_path')->nullable()->after('stamp_path');
            $table->boolean('use_watermark')->default(false)->after('use_stamp');
        });
    }

    public function down(): void
    {
        Schema::table('facility_settings', function (Blueprint $table) {
            $table->dropColumn(['watermark_path', 'use_watermark']);
        });
    }
};
