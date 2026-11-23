<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Fillable(['duration_minutes', 'allow_question_navigation', 'trainable_type', 'trainable_id', 'question_bank_quiz_id', 'type', 'title_ar', 'title_en', 'instructions_ar', 'instructions_en', 'position', 'status', 'is_required', 'passing_score', 'available_from', 'available_until', 'max_retries', 'retry_delay_minutes', 'allow_retake_after_pass', 'randomize_options', 'show_correct_answers', 'show_explanations', 'require_all_answers', 'randomize_questions', 'question_limit', 'excluded_question_ids'])]
class Training extends Model
{
    protected function casts(): array
    {
        return ['duration_minutes' => 'integer', 'allow_question_navigation' => 'boolean', 'is_required' => 'boolean', 'available_from' => 'datetime', 'available_until' => 'datetime', 'allow_retake_after_pass' => 'boolean', 'randomize_options' => 'boolean', 'show_correct_answers' => 'boolean', 'show_explanations' => 'boolean', 'require_all_answers' => 'boolean', 'max_retries' => 'integer', 'retry_delay_minutes' => 'integer', 'question_limit' => 'integer', 'randomize_questions' => 'boolean', 'excluded_question_ids' => 'array'];
    }

    public function trainable()
    {
        return $this->morphTo();
    }

    public function questions()
    {
        return $this->belongsToMany(QuestionBankQuestion::class)->withPivot('position')->orderByPivot('position');
    }

    public function quiz()
    {
        return $this->belongsTo(QuestionBankQuiz::class, 'question_bank_quiz_id');
    }

    public function attempts()
    {
        return $this->hasMany(TrainingAttempt::class);
    }

    public function drafts()
    {
        return $this->hasMany(TrainingDraft::class);
    }

    public function prerequisites()
    {
        return $this->hasMany(TrainingPrerequisite::class);
    }

    public function questionSet(): Collection
    {
        $questions = $this->question_bank_quiz_id && $this->quiz ? $this->quiz->questions : $this->questions;

        return $questions->filter(fn ($question) => $question->is_active && ! in_array($question->id, $this->excluded_question_ids ?? []))->values();
    }
}
