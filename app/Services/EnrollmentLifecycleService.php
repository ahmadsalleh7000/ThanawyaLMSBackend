<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EnrollmentLifecycleService
{
    public function enroll(User $student, Course $course, string $source, ?CarbonInterface $expiresAt = null, ?User $actor = null, ?string $reason = null): Enrollment
    {
        return DB::transaction(function () use ($student, $course, $source, $expiresAt, $actor, $reason): Enrollment {
            $enrollment = Enrollment::query()->lockForUpdate()->firstOrNew(['user_id' => $student->id, 'course_id' => $course->id]);
            $action = $enrollment->exists ? 'reactivated' : 'enrolled';
            $enrollment->fill(['enrolled_at' => $enrollment->enrolled_at ?? now(), 'status' => 'active', 'access_expires_at' => $expiresAt, 'source' => $source, 'admin_note' => $reason])->save();
            $this->event($enrollment, $action, $actor, $reason, null, $course->id);

            return $enrollment;
        });
    }

    public function suspend(Enrollment $enrollment, User $actor, string $reason): void
    {
        $enrollment->update(['status' => 'suspended', 'admin_note' => $reason]);
        $this->event($enrollment, 'suspended', $actor, $reason);
    }

    public function activate(Enrollment $enrollment, User $actor, ?string $reason = null): void
    {
        $enrollment->update(['status' => 'active', 'admin_note' => $reason]);
        $this->event($enrollment, 'reactivated', $actor, $reason);
    }

    public function extend(Enrollment $enrollment, User $actor, CarbonInterface $expiresAt, ?string $reason = null): void
    {
        $enrollment->update(['status' => 'active', 'access_expires_at' => $expiresAt, 'admin_note' => $reason]);
        $this->event($enrollment, 'extended', $actor, $reason, metadata: ['access_expires_at' => $expiresAt->toIso8601String()]);
    }

    public function cancel(Enrollment $enrollment, User $actor, string $reason): void
    {
        $enrollment->update(['status' => 'cancelled', 'admin_note' => $reason]);
        $this->event($enrollment, 'cancelled', $actor, $reason);
    }

    public function transfer(Enrollment $enrollment, Course $target, User $actor, string $reason): Enrollment
    {
        $source = $enrollment->course_id;
        $enrollment->update(['status' => 'cancelled', 'admin_note' => $reason]);
        $this->event($enrollment, 'transferred_out', $actor, $reason, $source, $target->id);
        $new = $this->enroll($enrollment->user, $target, 'transfer', $enrollment->access_expires_at, $actor, $reason);
        $this->event($new, 'transferred_in', $actor, $reason, $source, $target->id);

        return $new;
    }

    private function event(Enrollment $enrollment, string $action, ?User $actor, ?string $reason = null, ?string $from = null, ?string $to = null, ?array $metadata = null): void
    {
        $enrollment->events()->create(['actor_id' => $actor?->id, 'action' => $action, 'reason' => $reason, 'from_course_id' => $from, 'to_course_id' => $to, 'metadata' => $metadata]);
    }
}
