<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('allow_question_navigation')->default(true);
        });
        Schema::create('training_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->json('answers');
            $table->json('question_snapshot')->nullable();
            $table->unsignedInteger('current_index')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['training_id', 'student_id']);
        });
        Schema::table('assessment_drafts', fn (Blueprint $table) => $table->json('question_snapshot')->nullable());
        Schema::table('question_bank_questions', function (Blueprint $table) {
            $table->unsignedTinyInteger('correct_option')->nullable()->change();
            $table->json('correct_options')->nullable();
            $table->text('correct_answer')->nullable();
        });
        foreach (['assessment_attempts', 'training_attempts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('draft_id')->nullable()->unique();
                $table->json('question_snapshot')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['assessment_attempts', 'training_attempts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['draft_id']);
                $table->dropColumn(['draft_id', 'question_snapshot']);
            });
        }
        Schema::dropIfExists('training_drafts');
        Schema::table('assessment_drafts', fn (Blueprint $table) => $table->dropColumn('question_snapshot'));
        Schema::table('question_bank_questions', fn (Blueprint $table) => $table->dropColumn(['correct_options', 'correct_answer']));
        Schema::table('trainings', fn (Blueprint $table) => $table->dropColumn(['duration_minutes', 'allow_question_navigation']));
    }
};
