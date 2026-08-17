<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE rooms CHANGE room_type room_type ENUM('normal', 'vip', 'operation') NOT NULL DEFAULT 'normal'");

            return;
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('room_type_new', ['normal', 'vip', 'operation'])->default('normal')->after('room_type');
        });

        DB::table('rooms')->update(['room_type_new' => DB::raw('room_type')]);

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('room_type');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->renameColumn('room_type_new', 'room_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('rooms')->where('room_type', 'operation')->update(['room_type' => 'normal']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE rooms CHANGE room_type room_type ENUM('normal', 'vip') NOT NULL DEFAULT 'normal'");

            return;
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('room_type_old', ['normal', 'vip'])->default('normal')->after('room_type');
        });

        DB::table('rooms')->update(['room_type_old' => DB::raw('room_type')]);

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('room_type');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->renameColumn('room_type_old', 'room_type');
        });
    }
};
