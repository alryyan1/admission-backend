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
            $table->unsignedBigInteger('role_id')->nullable()->after('role');
        });

        Schema::table('operation_team_members', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('role');
        });

        $roles = DB::table('team_roles')->pluck('id', 'slug');
        $otherRoleId = $roles->get('other');

        foreach ($roles as $slug => $id) {
            DB::table('doctors')->where('role', $slug)->update(['role_id' => $id]);
            DB::table('operation_team_members')->where('role', $slug)->update(['role_id' => $id]);
        }

        DB::table('doctors')->whereNull('role_id')->update(['role_id' => $otherRoleId]);
        DB::table('operation_team_members')->whereNull('role_id')->update(['role_id' => $otherRoleId]);

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('operation_team_members', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        // SQLite (used in tests) can't add foreign keys via ALTER; skip there,
        // same convention used for the doctors<->admissions/operations FKs.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('doctors', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('team_roles')->restrictOnDelete();
        });

        Schema::table('operation_team_members', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('team_roles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('doctors', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
            });

            Schema::table('operation_team_members', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
            });
        }

        Schema::table('doctors', function (Blueprint $table) {
            $table->string('role')->default('surgeon')->after('specialist');
        });

        Schema::table('operation_team_members', function (Blueprint $table) {
            $table->string('role')->nullable()->after('name');
        });

        $slugsById = DB::table('team_roles')->pluck('slug', 'id');

        foreach ($slugsById as $id => $slug) {
            DB::table('doctors')->where('role_id', $id)->update(['role' => $slug]);
            DB::table('operation_team_members')->where('role_id', $id)->update(['role' => $slug]);
        }

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('role_id');
        });

        Schema::table('operation_team_members', function (Blueprint $table) {
            $table->dropColumn('role_id');
        });
    }
};
