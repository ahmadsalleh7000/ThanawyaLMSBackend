<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'difficulty', 'topic', 'topic_ar', 'prompt', 'prompt_ar', 'options', 'options_ar', 'correct_option', 'explanation', 'explanation_ar', 'created_by', 'is_active'])]
class QuestionBankQuestion extends Model
{
    protected function casts(): array
    {
        return ['options' => 'array', 'options_ar' => 'array', 'is_active' => 'boolean'];
    }

    public function assessmentQuestions()
    {
        return $this->hasMany(AssessmentQuestion::class);
    }

    public function quizzes()
    {
        return $this->belongsToMany(QuestionBankQuiz::class, 'question_bank_question_quiz')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
