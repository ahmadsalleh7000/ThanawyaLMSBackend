<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name_ar', 'name_en', 'type', 'description_ar', 'description_en', 'passing_score', 'is_required', 'is_active', 'created_by'])]
class QuestionBankQuiz extends Model
{
    public const TYPES = ['training', 'required'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_active' => 'boolean'];
    }

    public function questions()
    {
        return $this->belongsToMany(QuestionBankQuestion::class, 'question_bank_question_quiz')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function trainings()
    {
        return $this->hasMany(Training::class);
    }
}
