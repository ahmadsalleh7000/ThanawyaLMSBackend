<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['trainable_type', 'trainable_id', 'question_bank_quiz_id', 'type', 'title_ar', 'title_en', 'instructions_ar', 'instructions_en', 'position', 'status', 'is_required', 'passing_score', 'available_from', 'available_until'])]
class Training extends Model
{
    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'available_from' => 'datetime', 'available_until' => 'datetime'];
    }

    public function trainable() { return $this->morphTo(); }
    public function questions() { return $this->belongsToMany(QuestionBankQuestion::class)->withPivot('position')->orderByPivot('position'); }
    public function quiz() { return $this->belongsTo(QuestionBankQuiz::class, 'question_bank_quiz_id'); }
    public function attempts() { return $this->hasMany(TrainingAttempt::class); }

    public function questionSet(): Collection
    {
        if ($this->question_bank_quiz_id && $this->relationLoaded('quiz') && $this->quiz) {
            return $this->quiz->questions;
        }

        return $this->questions;
    }
}
