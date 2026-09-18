<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\LiveSessionAttendance;
use App\Models\MediaAsset;
use App\Models\TeacherCourseAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveSessionController extends Controller
{
    public function store(Request $request, Course $course): JsonResponse
    {
        abort_unless(TeacherCourseAssignment::query()->active()->where('teacher_id', $request->user()->id)->where('course_id', $course->id)->exists(), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'title_en' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'], 'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'], 'meeting_url' => ['required', 'url', 'max:500'],
            'status' => ['sometimes', 'in:draft,published'],
        ]);
        $host = parse_url($data['meeting_url'], PHP_URL_HOST);
        abort_unless($host && $this->allowedHost($host), 422, 'Only approved meeting providers are allowed.');
        $session = LiveSession::create($data + ['status' => 'draft', 'course_id' => $course->id, 'created_by' => $request->user()->id]);

        return response()->json(['data' => $this->serialize($session)], 201);
    }

    public function attachRecording(Request $request, Course $course, LiveSession $session): JsonResponse
    {
        abort_unless(TeacherCourseAssignment::query()->active()->where('teacher_id', $request->user()->id)->where('course_id', $course->id)->exists(), 403);
        abort_unless($session->course_id === $course->id, 404);
        $data = $request->validate(['media_asset_id' => ['required', 'integer', 'exists:media_assets,id']]);
        $media = MediaAsset::query()->whereKey($data['media_asset_id'])->where('kind', 'video')->whereHas('lesson', fn ($query) => $query->where('course_id', $course->id))->firstOrFail();
        $session->update(['recording_media_id' => $media->id]);

        return response()->json(['data' => $this->serialize($session->refresh())]);
    }

    public function attendance(Request $request, Course $course, LiveSession $session): JsonResponse
    {
        $this->ensureAssigned($request, $course, $session);
        $rows = $course->enrollments()->active()->with('user:id,name,email')->get()->map(function ($enrollment) use ($session): array {
            $attendance = LiveSessionAttendance::query()->where('live_session_id', $session->id)->where('student_id', $enrollment->user_id)->first();

            return ['student' => ['id' => $enrollment->user->id, 'name' => $enrollment->user->name, 'email' => $enrollment->user->email], 'status' => $attendance?->status ?? 'unmarked', 'markedAt' => $attendance?->marked_at?->toISOString()];
        });

        return response()->json(['data' => $rows->values()]);
    }

    public function markAttendance(Request $request, Course $course, LiveSession $session): JsonResponse
    {
        $this->ensureAssigned($request, $course, $session);
        $data = $request->validate(['student_id' => ['required', 'integer', 'exists:users,id'], 'status' => ['required', 'in:present,absent,excused']]);
        abort_unless($course->enrollments()->active()->where('user_id', $data['student_id'])->exists(), 422, 'The learner is not active in this course.');
        $attendance = LiveSessionAttendance::updateOrCreate(['live_session_id' => $session->id, 'student_id' => $data['student_id']], ['marked_by' => $request->user()->id, 'status' => $data['status'], 'marked_at' => now()]);

        return response()->json(['data' => ['studentId' => $attendance->student_id, 'status' => $attendance->status, 'markedAt' => $attendance->marked_at->toISOString()]]);
    }

    private function allowedHost(string $host): bool
    {
        return collect(['zoom.us', 'meet.google.com', 'teams.microsoft.com'])->contains(fn ($allowed) => $host === $allowed || str_ends_with($host, '.'.$allowed));
    }

    private function ensureAssigned(Request $request, Course $course, LiveSession $session): void
    {
        abort_unless(TeacherCourseAssignment::query()->active()->where('teacher_id', $request->user()->id)->where('course_id', $course->id)->exists(), 403);
        abort_unless($session->course_id === $course->id, 404);
    }

    private function serialize(LiveSession $session): array
    {
        return ['id' => $session->id, 'title' => $session->title, 'titleEn' => $session->title_en, 'startsAt' => $session->starts_at?->toISOString(), 'endsAt' => $session->ends_at?->toISOString(), 'meetingUrl' => $session->meeting_url, 'recordingMediaId' => $session->recording_media_id, 'status' => $session->status];
    }
}
