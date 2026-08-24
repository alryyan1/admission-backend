<?php

namespace Database\Seeders;

use App\Models\TeamRole;
use Illuminate\Database\Seeder;

class TeamRoleSeeder extends Seeder
{
    public function run(): void
    {
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
            // slug and is_protected are guarded on the model (API can't set them),
            // so they need forceFill here rather than firstOrCreate.
            TeamRole::query()->firstOrNew(['slug' => $role['slug']])->forceFill($role)->save();
        }
    }
}
