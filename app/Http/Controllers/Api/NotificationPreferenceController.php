<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->serialize($request->user()->notificationPreference()->firstOrCreate()->refresh())]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_updates' => ['required', 'boolean'],
            'course_updates' => ['required', 'boolean'],
            'learning_reminders' => ['required', 'boolean'],
            'parent_progress_summaries' => ['required', 'boolean'],
            'quiet_hours_enabled' => ['required', 'boolean'],
            'quiet_hours_start' => ['required', 'date_format:H:i'],
            'quiet_hours_end' => ['required', 'date_format:H:i'],
            'reminder_time' => ['required', 'date_format:H:i'],
            'weekly_summary_day' => ['required', 'integer', 'between:0,6'],
            'timezone' => ['required', 'timezone', Rule::in(['Africa/Cairo'])],
        ]);
        $preference = $request->user()->notificationPreference()->firstOrCreate();
        $preference->update($data);

        return response()->json(['data' => $this->serialize($preference->refresh())]);
    }

    private function serialize(NotificationPreference $preference): array
    {
        return [
            'paymentUpdates' => $preference->payment_updates,
            'courseUpdates' => $preference->course_updates,
            'learningReminders' => $preference->learning_reminders,
            'parentProgressSummaries' => $preference->parent_progress_summaries,
            'quietHoursEnabled' => $preference->quiet_hours_enabled,
            'quietHoursStart' => substr($preference->quiet_hours_start, 0, 5),
            'quietHoursEnd' => substr($preference->quiet_hours_end, 0, 5),
            'reminderTime' => substr($preference->reminder_time, 0, 5),
            'weeklySummaryDay' => $preference->weekly_summary_day,
            'timezone' => $preference->timezone,
        ];
    }
}
