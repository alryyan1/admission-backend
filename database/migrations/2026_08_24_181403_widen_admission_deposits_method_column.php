<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * method now stores a free-form payment method name (from the payment_methods
     * table) instead of a fixed enum code, so it can no longer be a MySQL ENUM.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE admission_deposits MODIFY method VARCHAR(255) NOT NULL DEFAULT \'cash\'');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE admission_deposits SET method = 'cash' WHERE method NOT IN ('cash', 'bank', 'fawry', 'ocash')");
            DB::statement("ALTER TABLE admission_deposits MODIFY method ENUM('cash', 'bank', 'fawry', 'ocash') NOT NULL DEFAULT 'cash'");
        }
    }
};
