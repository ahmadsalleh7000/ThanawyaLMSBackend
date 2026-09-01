<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['lesson_id', 'type', 'title', 'content', 'sort_order', 'settings'])]
class LessonSection extends Model
{
    public const TYPES = [
        'heading', 'paragraph', 'introduction', 'objectives', 'key_terms', 'definition', 'explanation',
        'important_note', 'common_mistake', 'example', 'worked_example', 'comparison', 'table', 'image', 'code',
        'activity', 'question', 'question_group', 'summary', 'challenge', 'answer_key', 'page_break',
    ];

    public const DEFAULT_SETTINGS = [
        'show_on_web' => true,
        'show_in_workbook' => true,
        'show_in_teacher_edition' => false,
        'avoid_page_break_inside' => true,
        'page_break_before' => false,
        'page_break_after' => false,
    ];

    protected function casts(): array
    {
        return ['content' => 'array', 'settings' => 'array'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function visibleOnWeb(): bool
    {
        return (bool) (($this->settingsWithDefaults()['show_on_web']) ?? true);
    }

    public function visibleInWorkbook(): bool
    {
        return (bool) (($this->settingsWithDefaults()['show_in_workbook']) ?? true);
    }

    public function settingsWithDefaults(): array
    {
        return array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }

    public function text(): string
    {
        return (string) data_get($this->content, 'text', '');
    }

    public function items(): array
    {
        return array_values(array_filter((array) data_get($this->content, 'items', []), fn ($item) => filled($item)));
    }

    public function anchor(): string
    {
        return 'lesson-section-'.$this->id.'-'.Str::slug($this->title ?: $this->type);
    }
}
