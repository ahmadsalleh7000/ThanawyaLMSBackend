<?php

namespace App\Models;

use App\Enums\CourseScope;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['id', 'source_course_id', 'title', 'title_en', 'summary', 'summary_en', 'description', 'description_en', 'image_url', 'category', 'scope', 'academic_subject_id', 'position', 'grade', 'semester', 'unit', 'track_type', 'level', 'duration_minutes', 'price', 'rating', 'enrolled_count', 'instructor', 'instructor_en', 'instructor_profile_id', 'is_published', 'archived_at'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['scope' => CourseScope::class, 'price' => 'decimal:2', 'rating' => 'decimal:1', 'is_published' => 'boolean', 'archived_at' => 'datetime'];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_published', true)->whereNull('archived_at');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeGeneral(Builder $query): Builder
    {
        return $query->where('scope', CourseScope::General->value);
    }

    public function scopeAcademic(Builder $query): Builder
    {
        return $query->where('scope', CourseScope::Academic->value);
    }

    public function isGeneral(): bool
    {
        return $this->scope === CourseScope::General;
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    public function units(): HasMany { return $this->hasMany(Unit::class)->orderBy('position'); }
    public function trainings() { return $this->morphMany(Training::class, 'trainable')->orderBy('position'); }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function academicSubject(): BelongsTo
    {
        return $this->belongsTo(AcademicSubject::class);
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(Assessment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }

    public function instructorProfile(): BelongsTo
    {
        return $this->belongsTo(InstructorProfile::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CourseVersion::class)->latest();
    }

    public function scheduledActions(): HasMany
    {
        return $this->hasMany(ScheduledCourseAction::class)->latest('run_at');
    }
}
