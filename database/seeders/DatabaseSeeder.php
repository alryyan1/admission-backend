<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'مدير النظام',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $this->call([
            AishaBakhaitFacilitySeeder::class,
            ProcedureCatalogSeeder::class,
            ServiceSeeder::class,
            PaymentMethodSeeder::class,
            TeamRoleSeeder::class,
        ]);
    }
}
