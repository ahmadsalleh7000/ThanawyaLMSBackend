<?php

namespace App\Services;

use App\Models\AssessmentDraft;
use App\Models\TrainingDraft;

class ExamGradingService
{
    public function draftAnswers(array $answers, $questions, AssessmentDraft|TrainingDraft $draft, bool $allowNavigation): array
    {
        $normalized = $this->normalizeAnswers($answers, $questions);
        if ($allowNavigation) {
            return $normalized;
        }
        $result = [];
        foreach ($questions as $index => $question) {
            $value = $index < $draft->current_index ? ($draft->answers[$question->id] ?? null) : ($index === $draft->current_index ? ($normalized[$question->id] ?? null) : null);
            if ($value !== null) {
                $result[$question->id] = $value;
            }
        }

        return $result;
    }

    public function normalizeAnswers(array $answers, $questions): array
    {
        $normalized = [];
        foreach ($questions as $question) {
            $value = $answers[$question->id] ?? $answers[(string) $question->id] ?? null;
            if ($question->type === 'multiple_select') {
                if (! is_array($value)) {
                    continue;
                }
                if (! collect($value)->every(fn ($index) => is_int($index) || (is_string($index) && ctype_digit($index)))) {
                    continue;
                }
                $value = array_values(array_unique(array_map('intval', $value)));
                sort($value);
                if ($value !== [] && collect($value)->every(fn ($index) => $index >= 0 && $index < count($question->options ?? []))) {
                    $normalized[$question->id] = $value;
                }
            } elseif (in_array($question->type, ['short_answer', 'fill_in_blank'], true)) {
                if (is_string($value) && trim($value) !== '' && mb_strlen($value) <= 1000) {
                    $normalized[$question->id] = trim($value);
                }
            } elseif ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 0 && (int) $value < count($question->options ?? [])) {
                $normalized[$question->id] = (int) $value;
            }
        }

        return $normalized;
    }

    public function answerIsCorrect($question, mixed $answer): bool
    {
        if ($question->type === 'multiple_select') {
            if (! is_array($answer)) {
                return false;
            }
            $expected = array_map('intval', $question->correct_options ?? []);
            sort($expected);
            $answer = array_map('intval', $answer);
            sort($answer);

            return $answer === $expected;
        }

        if (in_array($question->type, ['short_answer', 'fill_in_blank'], true)) {
            return is_string($answer) && $this->normalizeTextAnswer($answer) === $this->normalizeTextAnswer((string) $question->correct_answer);
        }

        return $answer !== null && (int) $answer === (int) $question->correct_option;
    }

    public function normalizeTextAnswer(string $answer): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($answer)));
    }
}
