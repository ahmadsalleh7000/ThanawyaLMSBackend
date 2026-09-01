<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('payment_updates')->default(true);
            $table->boolean('course_updates')->default(true);
            $table->boolean('learning_reminders')->default(true);
            $table->boolean('parent_progress_summaries')->default(true);
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->default('22:00');
            $table->time('quiet_hours_end')->default('07:00');
            $table->time('reminder_time')->default('18:00');
            $table->unsignedTinyInteger('weekly_summary_day')->default(5);
            $table->string('timezone')->default('Africa/Cairo');
            $table->timestamp('last_learning_reminder_at')->nullable();
            $table->timestamp('last_parent_summary_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
