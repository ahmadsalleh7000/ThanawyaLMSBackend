<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['assessment_id', 'question_bank_question_id', 'type', 'difficulty', 'topic', 'topic_ar', 'prompt', 'prompt_ar', 'options', 'options_ar', 'correct_option', 'explanation', 'explanation_ar', 'position'])]
class AssessmentQuestion extends Model
{
    protected function casts(): array
    {
        return ['options' => 'array', 'options_ar' => 'array'];
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function bankQuestion()
    {
        return $this->belongsTo(QuestionBankQuestion::class, 'question_bank_question_id');
    }
}
