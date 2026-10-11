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
        Schema::table('team_roles', function (Blueprint $table) {
            // Whether this role is auto-added by the "+ الفريق الافتراضي" button
            // on an operation; configurable by an admin from the team roles settings page.
            $table->boolean('is_default_team_member')->default(false)->after('is_protected');
        });

        DB::table('team_roles')
            ->whereIn('slug', ['surgeon', 'anesthesiologist', 'assistant_surgeon', 'circulating_nurse'])
            ->update(['is_default_team_member' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_roles', function (Blueprint $table) {
            $table->dropColumn('is_default_team_member');
        });
    }
};
