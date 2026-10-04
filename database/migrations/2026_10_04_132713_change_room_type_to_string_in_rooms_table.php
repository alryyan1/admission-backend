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
            DB::statement("ALTER TABLE rooms CHANGE room_type room_type VARCHAR(50) NOT NULL DEFAULT 'normal'");

            return;
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_type_new', 50)->default('normal')->after('room_type');
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
        DB::table('rooms')
            ->whereNotIn('room_type', ['normal', 'vip', 'operation', 'ward'])
            ->update(['room_type' => 'normal']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE rooms CHANGE room_type room_type ENUM('normal', 'vip', 'operation', 'ward') NOT NULL DEFAULT 'normal'");

            return;
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('room_type_old', ['normal', 'vip', 'operation', 'ward'])->default('normal')->after('room_type');
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
