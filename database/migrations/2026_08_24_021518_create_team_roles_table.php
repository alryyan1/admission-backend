<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_roles', function (Blueprint $table) {
            $table->id();
            // Stable identifier for the seeded system roles so code can find
            // "the surgeon role" even if an admin renames it; null for any
            // role an admin adds afterwards.
            $table->string('slug')->nullable()->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            // True only for the surgeon row: it drives the admitting-doctor
            // and operation-surgeon pickers, so it can't be deleted.
            $table->boolean('is_protected')->default(false);
            $table->timestamps();
        });

        $now = now();
        $roles = [
            ['slug' => 'surgeon', 'name' => 'جراح', 'sort_order' => 0, 'is_protected' => true],
            ['slug' => 'assistant_surgeon', 'name' => 'مساعد جراح', 'sort_order' => 1, 'is_protected' => false],
            ['slug' => 'anesthesiologist', 'name' => 'طبيب تخدير', 'sort_order' => 2, 'is_protected' => false],
            ['slug' => 'scrub_nurse', 'name' => 'ممرض/ة تعقيم', 'sort_order' => 3, 'is_protected' => false],
            ['slug' => 'circulating_nurse', 'name' => 'ممرض/ة تداول', 'sort_order' => 4, 'is_protected' => false],
            ['slug' => 'technician', 'name' => 'فني', 'sort_order' => 5, 'is_protected' => false],
            ['slug' => 'other', 'name' => 'أخرى', 'sort_order' => 6, 'is_protected' => false],
        ];

        foreach ($roles as $role) {
            DB::table('team_roles')->insert([...$role, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_roles');
    }
};
