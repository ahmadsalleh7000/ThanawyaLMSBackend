<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\EnsuresNonProductionSeedEnvironment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminTestingSeeder extends Seeder
{
    use EnsuresNonProductionSeedEnvironment, WithoutModelEvents;

    public function run(): void
    {
        $this->ensureNonProductionSeedEnvironment();

        $this->call([
            AdminDashboardTestingSeeder::class,
            AdminNotificationDemoSeeder::class,
            QuestionBankTestingSeeder::class,
        ]);
    }
}
