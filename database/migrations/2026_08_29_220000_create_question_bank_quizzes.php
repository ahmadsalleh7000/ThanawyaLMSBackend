<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_quizzes', function (Blueprint $table): void {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('type')->default('training')->index();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->unsignedSmallInteger('passing_score')->default(70);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('question_bank_question_quiz', function (Blueprint $table): void {
            $table->foreignId('question_bank_quiz_id')->constrained('question_bank_quizzes')->cascadeOnDelete();
            $table->foreignId('question_bank_question_id')->constrained('question_bank_questions')->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->primary(['question_bank_quiz_id', 'question_bank_question_id'], 'question_bank_quiz_question_primary');
        });

        Schema::table('trainings', function (Blueprint $table): void {
            $table->foreignId('question_bank_quiz_id')->nullable()->after('trainable_id')->constrained('question_bank_quizzes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('question_bank_quiz_id');
        });

        Schema::dropIfExists('question_bank_question_quiz');
        Schema::dropIfExists('question_bank_quizzes');
    }
};
