<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\EnsuresNonProductionSeedEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminDemoSeeder extends Seeder
{
    use EnsuresNonProductionSeedEnvironment;

    public function run(): void
    {
        $this->ensureNonProductionSeedEnvironment();
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@ahmed-lms.test')],
            ['name' => env('ADMIN_NAME', 'LMS Administrator'), 'password' => env('ADMIN_PASSWORD', Str::password(24)), 'role' => 'admin'],
        );
    }
}
