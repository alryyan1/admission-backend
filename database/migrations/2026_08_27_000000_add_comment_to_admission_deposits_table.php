<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_deposits', function (Blueprint $table) {
            $table->string('comment')->nullable()->after('payment_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('admission_deposits', function (Blueprint $table) {
            $table->dropColumn('comment');
        });
    }
};
