<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_id', 'assigned_to', 'status', 'risk_level', 'risk_score', 'risk_signals', 'detected_at', 'next_follow_up_at', 'resolved_at'])]
class StudentSupportCase extends Model
{
    /**
     * Return risk signals in the structured shape expected by admin views.
     *
     * Older seeded records stored signal keys as plain strings. Keeping the
     * compatibility conversion here prevents presentation code from having to
     * understand multiple persistence formats.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function displayRiskSignals(): array
    {
        $labels = [
            'inactive' => 'انخفاض النشاط مؤخراً',
            'inactivity' => 'غياب النشاط مؤخراً',
            'low_progress' => 'تقدم تعليمي منخفض',
            'failed_assessments' => 'تعثر في التقييمات',
            'manual' => 'ملاحظة متابعة يدوية',
        ];

        return collect($this->risk_signals ?? [])->map(function (mixed $signal) use ($labels): array {
            if (is_array($signal)) {
                $key = (string) ($signal['key'] ?? 'unknown');

                return ['key' => $key, 'label' => (string) ($signal['label'] ?? $labels[$key] ?? $key)];
            }

            $key = (string) $signal;

            return ['key' => $key, 'label' => $labels[$key] ?? $key];
        })->all();
    }

    protected function casts(): array
    {
        return ['risk_signals' => 'array', 'detected_at' => 'datetime', 'next_follow_up_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities()
    {
        return $this->hasMany(StudentSupportActivity::class)->latest();
    }
}
