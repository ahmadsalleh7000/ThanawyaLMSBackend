<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminStaffController extends Controller
{
    public function index(): View
    {
        return view('admin.staff.index', [
            'staff' => User::where('role', User::ROLE_ADMIN)->latest()->paginate(20),
            'roles' => config('admin_roles.roles'),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'admin_role' => ['required', Rule::in(array_keys(config('admin_roles.roles'))), Rule::notIn(['super_admin'])],
        ]);
        $staff = User::create($data + ['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $audit->record($request, 'staff.created', $staff);

        return back()->with('success', 'تم إنشاء حساب الموظف بصلاحيات محددة.');
    }

    public function update(Request $request, User $staff, AuditService $audit): RedirectResponse
    {
        abort_unless($staff->isAdmin(), 404);
        abort_if($staff->id === $request->user()->id, 409, 'You cannot change your own role.');
        $data = $request->validate(['admin_role' => ['required', Rule::in(array_keys(config('admin_roles.roles')))]]);
        abort_if($staff->adminRole() === 'super_admin' && $data['admin_role'] !== 'super_admin' && $this->activeSuperAdmins() <= 1, 409, 'The final active super administrator cannot be demoted.');
        $before = $staff->attributesToArray();
        $staff->update($data);
        $audit->record($request, 'staff.role_updated', $staff, $before);

        return back()->with('success', 'تم تحديث دور الموظف.');
    }

    public function toggle(Request $request, User $staff, AuditService $audit): RedirectResponse
    {
        abort_unless($staff->isAdmin(), 404);
        abort_if($staff->id === $request->user()->id, 409, 'You cannot disable your own account.');
        abort_if($staff->is_active && $staff->adminRole() === 'super_admin' && $this->activeSuperAdmins() <= 1, 409, 'The final active super administrator cannot be disabled.');
        $before = $staff->attributesToArray();
        $staff->update(['is_active' => ! $staff->is_active]);
        $audit->record($request, $staff->is_active ? 'staff.activated' : 'staff.deactivated', $staff, $before);

        return back()->with('success', 'تم تحديث حالة حساب الموظف.');
    }

    private function activeSuperAdmins(): int
    {
        return User::where('role', User::ROLE_ADMIN)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('admin_role')->orWhere('admin_role', 'super_admin'))
            ->count();
    }
}
