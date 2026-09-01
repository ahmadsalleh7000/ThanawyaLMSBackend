<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AnnouncementDeliveryService
{
    public function deliver(Announcement $announcement): int
    {
        if (in_array($announcement->status, ['published', 'cancelled'], true) || ($announcement->expires_at && $announcement->expires_at->isPast())) {
            return 0;
        }

        $delivered = 0;
        DB::transaction(function () use ($announcement, &$delivered): void {
            $this->recipients($announcement)->select('users.id')->orderBy('users.id')->chunkById(500, function ($users) use ($announcement, &$delivered): void {
                $now = now();
                $rows = $users->map(fn (User $user) => [
                    'user_id' => $user->id,
                    'announcement_id' => $announcement->id,
                    'type' => 'announcement',
                    'title' => $announcement->title,
                    'title_ar' => $announcement->title_ar,
                    'message' => $announcement->message,
                    'message_ar' => $announcement->message_ar,
                    'action_url' => $announcement->action_url,
                    'data' => json_encode(['targetType' => $announcement->target_type], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                $delivered += DB::table('user_notifications')->insertOrIgnore($rows);
            });
            $announcement->update(['status' => 'published', 'published_at' => now()]);
        });

        return $delivered;
    }

    private function recipients(Announcement $announcement): Builder
    {
        return User::query()->where('is_active', true)->where('role', '!=', User::ROLE_ADMIN)
            ->when(in_array($announcement->target_type, ['course', 'grade'], true), fn (Builder $query) => $query->where(fn (Builder $preference) => $preference->whereDoesntHave('notificationPreference')->orWhereHas('notificationPreference', fn (Builder $settings) => $settings->where('course_updates', true))))
            ->when($announcement->target_type === 'students', fn (Builder $query) => $query->students())
            ->when($announcement->target_type === 'parents', fn (Builder $query) => $query->parents())
            ->when($announcement->target_type === 'course', fn (Builder $query) => $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments->active()->where('course_id', $announcement->course_id)))
            ->when($announcement->target_type === 'grade', function (Builder $query) use ($announcement): void {
                $query->where(function (Builder $audience) use ($announcement): void {
                    $audience->whereHas('enrollments', fn (Builder $enrollments) => $enrollments->active()->whereHas('course', fn (Builder $course) => $course->where('grade', $announcement->grade)))
                        ->orWhereHas('linkedStudents', fn (Builder $links) => $links->where('parent_student_links.grade', $announcement->grade));
                });
            });
    }
}
