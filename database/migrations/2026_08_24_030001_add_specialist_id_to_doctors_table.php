<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->unsignedBigInteger('specialist_id')->nullable()->after('specialist');
        });

        $now = now();
        $specialistNames = DB::table('doctors')->whereNotNull('specialist')->distinct()->pluck('specialist');

        foreach ($specialistNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $specialistId = DB::table('specialists')->where('name', $name)->value('id');
            if (! $specialistId) {
                $specialistId = DB::table('specialists')->insertGetId([
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('doctors')->where('specialist', $name)->update(['specialist_id' => $specialistId]);
        }

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('specialist');
        });

        // SQLite (used in tests) can't add foreign keys via ALTER; skip there,
        // same convention used for the doctors<->team_roles FK.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('doctors', function (Blueprint $table) {
            $table->foreign('specialist_id')->references('id')->on('specialists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('doctors', function (Blueprint $table) {
                $table->dropForeign(['specialist_id']);
            });
        }

        Schema::table('doctors', function (Blueprint $table) {
            $table->string('specialist')->nullable()->after('name');
        });

        $namesById = DB::table('specialists')->pluck('name', 'id');

        foreach ($namesById as $id => $name) {
            DB::table('doctors')->where('specialist_id', $id)->update(['specialist' => $name]);
        }

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('specialist_id');
        });
    }
};
