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
        // SQLite (used in tests) defines foreign keys inline in the CREATE TABLE
        // statement and doesn't support dropping them via ALTER; dropColumn()
        // alone recreates the table without the column there. MySQL needs the
        // constraint dropped explicitly before the column itself.
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        Schema::table('operations', function (Blueprint $table) use ($isSqlite) {
            if (! $isSqlite) {
                $table->dropForeign(['payment_method_id']);
            }
            $table->dropColumn('payment_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        Schema::table('operations', function (Blueprint $table) use ($isSqlite) {
            $column = $table->foreignId('payment_method_id')->nullable()->after('price');
            if (! $isSqlite) {
                $column->constrained()->nullOnDelete();
            }
        });
    }
};
