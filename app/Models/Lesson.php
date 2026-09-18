<?php

namespace App\Models;

use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'unit_id', 'slug', 'title', 'title_en', 'duration_minutes', 'position', 'is_preview', 'status', 'published_at', 'available_from', 'available_until', 'video_url', 'content_pdf_enabled', 'comments_enabled', 'content_pdf_media_id', 'content_pdf_url', 'content_pdf_title', 'content_pdf_description', 'workbook_enabled', 'allow_print', 'allow_pdf_download', 'include_workbook_cover', 'include_workbook_info', 'include_workbook_toc', 'include_workbook_answer_key', 'objectives', 'key_points', 'summary', 'completion_rules', 'transcript', 'transcript_en', 'captions'])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_preview' => 'boolean', 'content_pdf_enabled' => 'boolean', 'comments_enabled' => 'boolean', 'workbook_enabled' => 'boolean', 'allow_print' => 'boolean', 'allow_pdf_download' => 'boolean', 'include_workbook_cover' => 'boolean', 'include_workbook_info' => 'boolean', 'include_workbook_toc' => 'boolean', 'include_workbook_answer_key' => 'boolean', 'published_at' => 'datetime', 'available_from' => 'datetime', 'available_until' => 'datetime', 'objectives' => 'array', 'key_points' => 'array', 'completion_rules' => 'array', 'transcript' => 'array', 'transcript_en' => 'array', 'captions' => 'array'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function contentPdfMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'content_pdf_media_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function workspaceStates(): HasMany
    {
        return $this->hasMany(LessonWorkspaceState::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(LessonComment::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(LessonFeedback::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(LessonRevision::class)->latest();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(LessonSection::class)->ordered();
    }

    public function trainings()
    {
        return $this->morphMany(Training::class, 'trainable')->orderBy('position');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class)->orderBy('position');
    }

    public function prerequisites()
    {
        return $this->hasMany(LessonPrerequisite::class);
    }
}
