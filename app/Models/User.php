<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone_e164', 'phone_verified_at', 'password', 'must_change_password', 'temporary_password_expires_at', 'password_changed_at', 'role', 'admin_role', 'is_active', 'registration_status', 'registration_reviewed_at', 'registration_reviewed_by', 'registration_rejection_reason', 'anonymized_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_PARENT = 'parent';

    public const ROLE_STUDENT = 'student';

    public const ROLE_TEACHER = 'teacher';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function scopeStudents(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_STUDENT);
    }

    public function scopeParents(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_PARENT);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function registrationStatus(): RegistrationStatus
    {
        return RegistrationStatus::tryFrom((string) $this->registration_status)
            ?? ($this->is_active ? RegistrationStatus::Approved : RegistrationStatus::PendingPaymentReview);
    }

    public function isEligibleForAccess(): bool
    {
        return $this->is_active
            && ($this->role !== self::ROLE_STUDENT || $this->registrationStatus()->isEligibleForAccess());
    }

    public function registrationReviewer()
    {
        return $this->belongsTo(self::class, 'registration_reviewed_by');
    }

    public function adminRole(): ?string
    {
        if (! $this->isAdmin()) {
            return null;
        }

        return $this->admin_role ?: 'super_admin';
    }

    public function hasAdminPermission(string $permission): bool
    {
        $role = $this->adminRole();
        if (! $role) {
            return false;
        }
        $permissions = config("admin_roles.roles.{$role}.permissions", []);

        return in_array('*', $permissions, true) || $permission === 'admin.access' || in_array($permission, $permissions, true);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function studentSubscriptions(): HasMany
    {
        return $this->hasMany(StudentSubscription::class, 'student_id');
    }

    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function lessonCompletions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function lessonWorkspaceStates(): HasMany
    {
        return $this->hasMany(LessonWorkspaceState::class);
    }

    public function lessonComments(): HasMany
    {
        return $this->hasMany(LessonComment::class);
    }

    public function instructorProfile()
    {
        return $this->hasOne(InstructorProfile::class);
    }

    public function teacherCourseAssignments(): HasMany
    {
        return $this->hasMany(TeacherCourseAssignment::class, 'teacher_id');
    }

    public function assessmentAttempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function trainingAttempts(): HasMany
    {
        return $this->hasMany(TrainingAttempt::class, 'student_id');
    }

    public function assessmentDrafts(): HasMany
    {
        return $this->hasMany(AssessmentDraft::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function courseReviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }

    public function supportCase()
    {
        return $this->hasOne(StudentSupportCase::class, 'student_id');
    }

    public function linkedStudents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student_links', 'parent_id', 'student_id')
            ->wherePivot('status', 'accepted')
            ->where('users.role', self::ROLE_STUDENT)
            ->where('users.is_active', true)
            ->withPivot(['grade', 'status', 'linked_at', 'accepted_at'])->withTimestamps();
    }

    public function cohorts(): BelongsToMany
    {
        return $this->belongsToMany(Cohort::class, 'cohort_members', 'student_id', 'cohort_id')->withPivot('added_by')->withTimestamps();
    }

    public function linkedParents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student_links', 'student_id', 'parent_id')
            ->wherePivot('status', 'accepted')
            ->where('users.role', self::ROLE_PARENT)
            ->where('users.is_active', true);
    }

    public function guardianInvitations(): HasMany
    {
        return $this->hasMany(GuardianInvitation::class, 'student_id');
    }

    public function receivedGuardianInvitations(): HasMany
    {
        return $this->hasMany(GuardianInvitation::class, 'parent_user_id');
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function wishlistCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'wishlist_items')->withTimestamps();
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function notificationPreference()
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'beneficiary_user_id');
    }

    public function onboardingProfile()
    {
        return $this->hasOne(StudentOnboardingProfile::class, 'student_id');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'temporary_password_expires_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'is_active' => 'boolean',
            'registration_reviewed_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }
}
