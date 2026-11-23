<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Training;
use Illuminate\Support\Collection;

class ExamDeliveryService
{
    public function state(Assessment|Training $exam, int $userId, ?Collection $attempts = null): array
    {
        $isExam = $exam instanceof Assessment;
        if ($attempts !== null) {
            $attempts = $attempts->where($isExam ? 'user_id' : 'student_id', $userId)->whereNotNull('submitted_at');
            $used = $attempts->count();
            $passed = $attempts->contains(fn ($attempt) => $isExam ? $attempt->passed : $attempt->status === 'passed');
            $latest = $attempts->sortByDesc('submitted_at')->first();
        } else {
            $query = $exam->attempts()->where($isExam ? 'user_id' : 'student_id', $userId)->whereNotNull('submitted_at');
            $used = (clone $query)->count();
            $passed = $isExam ? (clone $query)->where('passed', true)->exists() : (clone $query)->where('status', 'passed')->exists();
            $latest = (clone $query)->latest('submitted_at')->first();
        }
        $max = $exam->max_retries === null ? null : $exam->max_retries + 1;
        $retryAt = $latest && $exam->retry_delay_minutes ? $latest->submitted_at->copy()->addMinutes($exam->retry_delay_minutes) : null;
        $reason = $max !== null && $used >= $max ? 'attempt_limit'
            : (! ($exam->allow_retake_after_pass ?? true) && $passed ? 'already_passed'
                : ($retryAt?->isFuture() ? 'retry_delay' : null));

        return [
            'maxRetries' => $exam->max_retries, 'maxAttempts' => $max, 'attemptsUsed' => $used,
            'attemptsRemaining' => $max === null ? null : max(0, $max - $used),
            'canAttempt' => $reason === null, 'blockedReason' => $reason,
            'retryAvailableAt' => $retryAt?->isFuture() ? $retryAt->toISOString() : null,
        ];
    }

    public function ensureCanAttempt(Assessment|Training $exam, int $userId): void
    {
        $state = $this->state($exam, $userId);
        abort_unless($state['canAttempt'], 422, match ($state['blockedReason']) {
            'attempt_limit' => __('No attempts remaining.'),
            'already_passed' => __('Retakes after passing are disabled.'),
            default => 'Wait until '.$state['retryAvailableAt'].' before retrying.',
        });
    }

    public function questions(Assessment|Training $exam, int $userId, ?array $snapshot = null): Collection
    {
        if ($snapshot !== null) {
            return collect($snapshot)->map(fn ($attributes) => (new AssessmentQuestion)->forceFill($attributes));
        }
        $questions = ($exam instanceof Assessment ? $exam->questions->where('is_active', true) : $exam->questionSet())->values();
        $seed = get_class($exam).':'.$exam->id.':'.$userId;
        if ($exam->randomize_questions) {
            $questions = $questions->sortBy(fn ($question) => sprintf('%u', crc32($exam->id.':'.$userId.':'.$question->id)))->values();
        }
        if ($exam->question_limit) {
            $questions = $questions->take($exam->question_limit)->values();
        }

        return $questions->map(function ($source) use ($exam, $seed) {
            $question = clone $source;
            $options = $question->options ?? [];
            $order = array_keys($options);
            if ($exam->randomize_options && ! in_array($question->type, ['true_false', 'short_answer', 'fill_in_blank'], true)) {
                usort($order, fn ($a, $b) => strcmp(hash('sha256', $seed.':'.$question->id.':'.$a), hash('sha256', $seed.':'.$question->id.':'.$b)));
            }
            $question->setAttribute('option_order', $order);
            $question->options = array_map(fn ($index) => $options[$index], $order);
            $question->options_ar = $question->options_ar ? array_map(fn ($index) => $source->options_ar[$index] ?? $options[$index], $order) : null;
            if ($question->correct_option !== null) {
                $question->correct_option = array_search((int) $source->correct_option, $order, true);
            }
            if ($question->correct_options !== null) {
                $question->correct_options = array_map(fn ($index) => array_search((int) $index, $order, true), $source->correct_options);
            }

            return $question;
        });
    }

    public function snapshot(Collection $questions): array
    {
        return $questions->map(fn ($q) => $q->only(['id', 'type', 'difficulty', 'topic', 'topic_ar', 'prompt', 'prompt_ar', 'options', 'options_ar', 'option_order', 'correct_option', 'correct_options', 'correct_answer', 'explanation', 'explanation_ar']))->all();
    }

    public function canonicalAnswers(array $answers, Collection $questions): array
    {
        $canonical = [];
        foreach ($questions as $question) {
            if (! array_key_exists($question->id, $answers)) {
                continue;
            }
            $answer = $answers[$question->id];
            $order = $question->getAttribute('option_order');
            $canonical[$question->id] = in_array($question->type, ['short_answer', 'fill_in_blank'], true) ? $answer
                : (is_array($answer) ? array_map(fn ($index) => $order[$index] ?? -1, $answer) : ($order[$answer] ?? -1));
        }

        return $canonical;
    }

    public function displayedAnswers(array $answers, Collection $questions): array
    {
        $displayed = [];
        foreach ($questions as $question) {
            if (! array_key_exists($question->id, $answers)) {
                continue;
            }
            $value = $answers[$question->id];
            $order = $question->getAttribute('option_order');
            $displayed[$question->id] = in_array($question->type, ['short_answer', 'fill_in_blank'], true) ? $value
                : (is_array($value) ? array_map(fn ($index) => array_search((int) $index, $order, true), $value) : array_search((int) $value, $order, true));
        }

        return $displayed;
    }

    public function review(Assessment|Training $exam, Collection $review): Collection
    {
        // Explanations can reveal answers, so both must be enabled to release them.
        if (! ($exam->show_correct_answers ?? true)) {
            return collect();
        }
        if (! ($exam->show_explanations ?? true)) {
            return $review->map(fn ($item) => collect($item)->except(['explanation', 'explanationAr'])->all());
        }

        return $review;
    }
}
