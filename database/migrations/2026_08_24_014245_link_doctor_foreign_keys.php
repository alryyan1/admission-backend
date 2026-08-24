<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (used in tests) defines foreign keys inline in the CREATE
        // TABLE statement and can't have them added via ALTER; skip there,
        // matching the convention already used when the doctors table was
        // previously dropped (see 2026_08_19_222814_drop_doctors_table.php).
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // While doctors were resolved live from Jawda Medical, admitting_doctor_id
        // and surgeon_id kept storing that external id even though no local row
        // existed. Backfill a placeholder local row per referenced id so the FK
        // constraints below don't fail against pre-existing data; admins can
        // rename these via the Doctors settings page afterwards.
        $referencedIds = DB::table('admissions')->whereNotNull('admitting_doctor_id')->pluck('admitting_doctor_id')
            ->merge(DB::table('operations')->pluck('surgeon_id'))
            ->unique()
            ->values();

        $existingIds = DB::table('doctors')->whereIn('id', $referencedIds)->pluck('id');

        foreach ($referencedIds->diff($existingIds) as $id) {
            DB::table('doctors')->insert([
                'id' => $id,
                'name' => "طبيب #{$id}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('admissions', function (Blueprint $table) {
            $table->foreign('admitting_doctor_id')->references('id')->on('doctors')->nullOnDelete();
        });

        Schema::table('operations', function (Blueprint $table) {
            $table->foreign('surgeon_id')->references('id')->on('doctors')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropForeign(['admitting_doctor_id']);
        });

        Schema::table('operations', function (Blueprint $table) {
            $table->dropForeign(['surgeon_id']);
        });
    }
};
