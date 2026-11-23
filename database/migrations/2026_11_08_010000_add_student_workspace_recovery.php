<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_onboarding_profiles', function (Blueprint $table) {
            $table->json('learning_goals')->nullable();
        });
        Schema::table('lesson_workspace_states', function (Blueprint $table) {
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamp('video_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_onboarding_profiles', fn (Blueprint $table) => $table->dropColumn('learning_goals'));
        Schema::table('lesson_workspace_states', fn (Blueprint $table) => $table->dropColumn(['last_opened_at', 'video_confirmed_at']));
    }
};
