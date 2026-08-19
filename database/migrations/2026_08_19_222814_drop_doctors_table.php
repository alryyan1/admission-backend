<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remap existing FK values from the local doctors.id to jawda_doctor_id
        // before the local table is dropped, so admitting_doctor_id/surgeon_id
        // keep pointing at a meaningful (Jawda Medical) doctor id. Done as a
        // per-row loop (not UPDATE...JOIN) so it also works on SQLite (tests).
        foreach (DB::table('doctors')->whereNotNull('jawda_doctor_id')->get(['id', 'jawda_doctor_id']) as $doctor) {
            DB::table('admissions')->where('admitting_doctor_id', $doctor->id)->update(['admitting_doctor_id' => $doctor->jawda_doctor_id]);
            DB::table('operations')->where('surgeon_id', $doctor->id)->update(['surgeon_id' => $doctor->jawda_doctor_id]);
        }

        // SQLite (used in tests) defines foreign keys inline in the CREATE
        // TABLE statement and can't ALTER them away without recreating the
        // table; it would otherwise keep enforcing admitting_doctor_id/
        // surgeon_id against the now-dropped doctors table on every insert.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            Schema::table('admissions', function (Blueprint $table) {
                $table->dropForeign(['admitting_doctor_id']);
            });

            Schema::table('operations', function (Blueprint $table) {
                $table->dropForeign(['surgeon_id']);
            });
        }

        Schema::dropIfExists('doctors');
    }

    public function down(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jawda_doctor_id')->nullable()->unique();
            $table->string('name');
            $table->string('specialist')->nullable();
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('admissions', function (Blueprint $table) {
                $table->foreign('admitting_doctor_id')->references('id')->on('doctors')->nullOnDelete();
            });

            Schema::table('operations', function (Blueprint $table) {
                $table->foreign('surgeon_id')->references('id')->on('doctors')->restrictOnDelete();
            });
        }
    }
};
