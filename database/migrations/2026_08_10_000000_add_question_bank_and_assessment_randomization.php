<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_questions', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('single_choice')->index();
            $table->string('difficulty')->default('medium')->index();
            $table->string('topic')->index();
            $table->string('topic_ar');
            $table->text('prompt');
            $table->text('prompt_ar');
            $table->json('options');
            $table->json('options_ar');
            $table->unsignedTinyInteger('correct_option');
            $table->text('explanation');
            $table->text('explanation_ar');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->boolean('randomize_questions')->default(false)->after('passing_score');
            $table->unsignedSmallInteger('question_limit')->nullable()->after('randomize_questions');
        });

        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->foreignId('question_bank_question_id')->nullable()->after('assessment_id')->constrained()->nullOnDelete();
            $table->string('type')->default('single_choice')->after('question_bank_question_id')->index();
            $table->string('difficulty')->default('medium')->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('question_bank_question_id');
            $table->dropColumn(['type', 'difficulty']);
        });
        Schema::table('assessments', fn (Blueprint $table) => $table->dropColumn(['randomize_questions', 'question_limit']));
        Schema::dropIfExists('question_bank_questions');
    }
};
