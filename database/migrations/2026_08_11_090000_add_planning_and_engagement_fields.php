<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->timestamp('available_from')->nullable()->after('is_published')->index();
            $table->timestamp('due_at')->nullable()->after('available_from')->index();
        });
        Schema::table('student_onboarding_profiles', function (Blueprint $table): void {
            $table->boolean('leaderboard_opt_in')->default(false)->after('weekly_goal_minutes')->index();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', fn (Blueprint $table) => $table->dropColumn(['available_from', 'due_at']));
        Schema::table('student_onboarding_profiles', fn (Blueprint $table) => $table->dropColumn('leaderboard_opt_in'));
    }
};
