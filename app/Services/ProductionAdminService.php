<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductionAdminService
{
    public function hasActiveSuperAdmin(): bool
    {
        return $this->superAdmins()->where('is_active', true)->exists();
    }

    public function hasConfiguredCredentials(): bool
    {
        return $this->credentialsValidator()->passes();
    }

    /** Return whether the first super administrator was created. */
    public function ensure(): bool
    {
        if ($this->hasActiveSuperAdmin()) {
            return false;
        }

        if ($this->superAdmins()->exists()) {
            throw new DomainException('An existing super administrator is inactive. Resolve its account status before running production setup.');
        }

        $credentials = $this->credentialsValidator()->validate();
        if (User::whereRaw('LOWER(email) = ?', [$credentials['email']])->exists()) {
            throw new DomainException('ADMIN_EMAIL belongs to an existing account. Production setup will not change its role, status, or password.');
        }

        $admin = new User([
            ...$credentials,
            'role' => User::ROLE_ADMIN,
            'admin_role' => 'super_admin',
            'is_active' => true,
            'registration_status' => 'approved',
            'must_change_password' => false,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        AuditLog::create([
            'action' => 'production.admin.created',
            'auditable_type' => $admin->getMorphClass(),
            'auditable_id' => (string) $admin->id,
            'after' => $admin->only(['email', 'role', 'admin_role', 'is_active']),
            'user_agent' => 'artisan production:setup',
        ]);

        return true;
    }

    private function superAdmins(): Builder
    {
        return User::where('role', User::ROLE_ADMIN)
            ->where(fn (Builder $query) => $query->whereNull('admin_role')->orWhereIn('admin_role', ['', 'super_admin']));
    }

    private function credentialsValidator(): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make([
            'name' => trim((string) config('deployment.admin_name')),
            'email' => mb_strtolower(trim((string) config('deployment.admin_email'))),
            'password' => config('deployment.admin_password'),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required', 'string', 'min:12', 'max:72', Rule::notIn(['TestingPassword123!']),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('ADMIN_PASSWORD must not exceed 72 bytes.');
                    }
                },
            ],
        ], [], [
            'name' => 'ADMIN_NAME',
            'email' => 'ADMIN_EMAIL',
            'password' => 'ADMIN_PASSWORD',
        ]);
    }
}
