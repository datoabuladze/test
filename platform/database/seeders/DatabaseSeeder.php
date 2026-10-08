<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            SiteSeeder::class,
            PageSeeder::class,
            AchievementSeeder::class,
            OriginalGamesSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
