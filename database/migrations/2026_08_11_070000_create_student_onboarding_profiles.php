<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_onboarding_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('grade')->nullable();
            $table->string('semester')->nullable();
            $table->string('learning_goal')->nullable();
            $table->string('experience_level')->nullable();
            $table->unsignedSmallInteger('weekly_goal_minutes')->default(120);
            $table->json('placement_answers')->nullable();
            $table->unsignedTinyInteger('placement_score')->nullable();
            $table->string('recommended_level')->nullable();
            $table->string('recommended_course_id')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
            $table->foreign('recommended_course_id')->references('id')->on('courses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_onboarding_profiles');
    }
};
