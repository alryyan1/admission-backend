<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE admission_deposits SET method = 'bank' WHERE method = 'bank_transfer'");
        DB::statement("UPDATE admission_deposits SET method = 'cash' WHERE method = 'card'");

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE admission_deposits MODIFY method ENUM('cash', 'bank', 'fawry', 'ocash') NOT NULL DEFAULT 'cash'");
        }
    }

    public function down(): void
    {
        DB::statement("UPDATE admission_deposits SET method = 'bank_transfer' WHERE method = 'bank'");
        DB::statement("UPDATE admission_deposits SET method = 'cash' WHERE method IN ('fawry', 'ocash')");

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE admission_deposits MODIFY method ENUM('cash', 'card', 'bank_transfer') NOT NULL DEFAULT 'cash'");
        }
    }
};
