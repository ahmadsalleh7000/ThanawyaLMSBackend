<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'users' => User::count(),
            'students' => User::students()->count(),
            'parents' => User::parents()->count(),
            'courses' => Course::notArchived()->count(),
            'publishedCourses' => Course::available()->count(),
            'enrollments' => Enrollment::count(),
            'revenue' => (float) Order::where('status', 'completed')->sum('amount'),
            'currency' => config('payments.currency'),
        ]]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $limit = min(max($request->integer('limit', 30), 1), 100);
        $logs = AuditLog::query()->with('user:id,name,email')->latest()->limit($limit)->get()->map(fn (AuditLog $log) => [
            'id' => $log->id, 'action' => $log->action, 'entityType' => $log->auditable_type,
            'entityId' => $log->auditable_id, 'actor' => $log->user?->only(['id', 'name', 'email']),
            'date' => $log->created_at->toISOString(),
        ]);

        return response()->json(['data' => $logs]);
    }
}
