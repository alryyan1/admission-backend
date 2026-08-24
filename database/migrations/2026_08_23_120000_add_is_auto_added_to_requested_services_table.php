<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requested_services', function (Blueprint $table) {
            $table->boolean('is_auto_added')->default(false)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('requested_services', function (Blueprint $table) {
            $table->dropColumn('is_auto_added');
        });
    }
};
