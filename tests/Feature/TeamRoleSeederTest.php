<?php

namespace Tests\Feature;

use App\Models\TeamRole;
use Database\Seeders\TeamRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Slugs the operation team modal adds with its "الفريق الافتراضي" button.
     */
    private const DEFAULT_TEAM_ROLE_SLUGS = [
        'surgeon' => 'جراح',
        'anesthesiologist' => 'طبيب تخدير',
        'assistant_surgeon' => 'مساعد جراح',
        'circulating_nurse' => 'ممرض/ة تداول',
    ];

    public function test_it_creates_the_default_team_roles(): void
    {
        $this->seed(TeamRoleSeeder::class);

        foreach (self::DEFAULT_TEAM_ROLE_SLUGS as $slug => $name) {
            $this->assertDatabaseHas('team_roles', [
                'slug' => $slug,
                'name' => $name,
            ]);
        }
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(TeamRoleSeeder::class);
        $countAfterFirstSeed = TeamRole::query()->count();

        $this->seed(TeamRoleSeeder::class);

        $this->assertSame($countAfterFirstSeed, TeamRole::query()->count());
        $this->assertSame(1, TeamRole::query()->where('slug', 'surgeon')->count());
    }

    public function test_the_surgeon_role_is_protected(): void
    {
        $this->seed(TeamRoleSeeder::class);

        $this->assertTrue(TeamRole::query()->where('slug', 'surgeon')->firstOrFail()->is_protected);
    }
}
