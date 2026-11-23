<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['assessments', 'trainings'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->unsignedSmallInteger('max_retries')->nullable();
                $table->unsignedInteger('retry_delay_minutes')->default(0);
                $table->boolean('allow_retake_after_pass')->default(true);
                $table->boolean('randomize_options')->default(false);
                $table->boolean('show_correct_answers')->default(true);
                $table->boolean('show_explanations')->default(true);
                $table->boolean('require_all_answers')->default(false);
                if ($name === 'trainings') {
                    $table->boolean('randomize_questions')->default(false);
                    $table->unsignedSmallInteger('question_limit')->nullable();
                    $table->json('excluded_question_ids')->nullable();
                } else {
                    $table->boolean('allow_question_navigation')->default(true);
                }
            });
        }
        Schema::table('assessment_questions', fn (Blueprint $table) => $table->boolean('is_active')->default(true));
    }

    public function down(): void
    {
        Schema::table('assessment_questions', fn (Blueprint $table) => $table->dropColumn('is_active'));
        foreach (['assessments', 'trainings'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropColumn(array_merge(['max_retries', 'retry_delay_minutes', 'allow_retake_after_pass', 'randomize_options', 'show_correct_answers', 'show_explanations', 'require_all_answers'], $name === 'trainings' ? ['randomize_questions', 'question_limit', 'excluded_question_ids'] : ['allow_question_navigation']));
            });
        }
    }
};
