<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'course_id', 'enrolled_at', 'status', 'access_expires_at', 'source', 'admin_note'])]
class Enrollment extends Model
{
    protected function casts(): array
    {
        return ['enrolled_at' => 'datetime', 'access_expires_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where(fn (Builder $inner) => $inner->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now()));
    }

    public function hasAccess(): bool
    {
        return $this->status === 'active' && (! $this->access_expires_at || $this->access_expires_at->isFuture());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EnrollmentEvent::class)->latest('created_at');
    }
}
