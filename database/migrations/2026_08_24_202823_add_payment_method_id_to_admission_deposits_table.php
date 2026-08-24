<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the free-text method column with a real relation to
     * payment_methods, so deposits stay consistent with the managed catalog.
     */
    public function up(): void
    {
        Schema::table('admission_deposits', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('method')->constrained('payment_methods')->nullOnDelete();
        });

        // Backfill: every distinct legacy method value becomes (or matches) a
        // payment_methods row by that exact name, so no historical data is lost.
        $distinctMethods = DB::table('admission_deposits')->whereNotNull('method')->distinct()->pluck('method');
        $now = now();

        foreach ($distinctMethods as $methodName) {
            $paymentMethodId = DB::table('payment_methods')->where('name', $methodName)->value('id');

            if (! $paymentMethodId) {
                $paymentMethodId = DB::table('payment_methods')->insertGetId([
                    'name' => $methodName,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('admission_deposits')->where('method', $methodName)->update(['payment_method_id' => $paymentMethodId]);
        }

        Schema::table('admission_deposits', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }

    public function down(): void
    {
        Schema::table('admission_deposits', function (Blueprint $table) {
            $table->string('method')->nullable()->after('amount');
        });

        DB::statement('
            UPDATE admission_deposits
            JOIN payment_methods ON payment_methods.id = admission_deposits.payment_method_id
            SET admission_deposits.method = payment_methods.name
        ');

        Schema::table('admission_deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
        });
    }
};
