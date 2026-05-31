<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MedalSeeder::class,
            ClubSeeder::class,
            UserSeeder::class,
            TestDataSeeder::class,
            TagSeeder::class,
            CaveSeeder::class,
            HutSeeder::class,
            PageSeeder::class,
            PrivacyPolicySeeder::class,
            OfflineModeCmsPageSeeder::class,
            CatchmentSeeder::class,
            RouteSeeder::class,
            SuggestedEditSeeder::class,
            AssistantDemoSeeder::class,
        ]);
    }
}
