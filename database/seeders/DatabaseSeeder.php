<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\EnsuresNonProductionSeedEnvironment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use EnsuresNonProductionSeedEnvironment, WithoutModelEvents;

    public function run(): void
    {
        $this->ensureNonProductionSeedEnvironment();

        $this->call([
            LocalDevelopmentSeeder::class,
            AdminDemoSeeder::class,
            ParentDemoSeeder::class,
            EngagementDemoSeeder::class,
            AdminDashboardTestingSeeder::class,
            AdminNotificationDemoSeeder::class,
            QuestionBankTestingSeeder::class,
        ]);
    }
}
