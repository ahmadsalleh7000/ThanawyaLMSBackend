<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\EnsuresNonProductionSeedEnvironment;
use Illuminate\Database\Seeder;

class ParentDemoSeeder extends Seeder
{
    use EnsuresNonProductionSeedEnvironment;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->ensureNonProductionSeedEnvironment();
        $student = User::whereIn('email', ['student@ahmed-lms.local', 'student@ahmed-lms.test'])->firstOrFail();
        $parent = User::updateOrCreate(
            ['email' => 'parent@ahmed-lms.local'],
            ['name' => 'Ahmed Parent', 'password' => 'password123', 'role' => User::ROLE_PARENT],
        );
        $parent->linkedStudents()->syncWithoutDetaching([
            $student->id => ['grade' => 'grade-1', 'linked_at' => now()],
        ]);
    }
}
