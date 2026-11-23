<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'difficulty', 'topic', 'topic_ar', 'prompt', 'prompt_ar', 'options', 'options_ar', 'correct_option', 'correct_options', 'correct_answer', 'explanation', 'explanation_ar', 'created_by', 'is_active'])]
class QuestionBankQuestion extends Model
{
    public const TYPES = ['single_choice', 'true_false', 'multiple_select', 'short_answer', 'fill_in_blank'];

    public const TYPE_LABELS = ['single_choice' => 'اختيار واحد', 'true_false' => 'صح أو خطأ', 'multiple_select' => 'اختيارات متعددة', 'short_answer' => 'إجابة قصيرة', 'fill_in_blank' => 'أكمل الفراغ'];

    protected function casts(): array
    {
        return ['options' => 'array', 'options_ar' => 'array', 'correct_options' => 'array', 'is_active' => 'boolean'];
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
