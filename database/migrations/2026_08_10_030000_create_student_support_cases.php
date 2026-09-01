<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_support_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open')->index();
            $table->string('risk_level')->default('medium')->index();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->json('risk_signals')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_support_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_support_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();
            $table->string('channel')->nullable();
            $table->text('body');
            $table->string('outcome')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_support_activities');
        Schema::dropIfExists('student_support_cases');
    }
};
