<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Course;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, AdminDashboardService $dashboard): View
    {
        $user = $request->user();
        $data = $dashboard->for($user);

        return view('admin.dashboard', [
            ...$data,
            'recentNotifications' => $user->userNotifications()->latest()->limit(6)->get(),
            'recentCourses' => $user->hasAdminPermission('content.manage') ? Course::withCount('lessons')->latest()->limit(5)->get() : collect(),
            'recentActivity' => AuditLog::with('user:id,name')->when(! $user->hasAdminPermission('system.manage'), fn ($query) => $query->where('user_id', $user->id))->latest()->limit(8)->get(),
        ]);
    }
}
