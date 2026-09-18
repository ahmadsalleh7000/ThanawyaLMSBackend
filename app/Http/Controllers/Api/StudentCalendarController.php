<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\UserNotification;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class StudentCalendarController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access) {}

    public function __invoke(Request $request): JsonResponse
    {
        $student = $request->user();
        $courseIds = $this->access->courseIdsFor($student);
        $courses = Course::available()->whereIn('id', $courseIds)->with(['lessons.assignments', 'assessment'])->get();
        $events = collect();

        foreach ($courses as $course) {
            foreach ($course->lessons as $lesson) {
                if ($lesson->available_from) {
                    $events->push(['id' => 'lesson-'.$lesson->id, 'type' => 'lesson', 'date' => $lesson->available_from->toISOString(), 'title' => $lesson->title, 'titleEn' => $lesson->title_en, 'course' => $course->title, 'courseEn' => $course->title_en, 'url' => '/course-player/'.$course->id.'?lesson='.$lesson->slug]);
                }
                foreach ($lesson->assignments->where('is_published', true) as $assignment) {
                    if ($assignment->due_at) {
                        $events->push(['id' => 'assignment-'.$assignment->id, 'type' => 'assignment', 'date' => $assignment->due_at->toISOString(), 'title' => $assignment->title, 'titleEn' => $assignment->title_en, 'course' => $course->title, 'courseEn' => $course->title_en, 'url' => '/course-player/'.$course->id.'?lesson='.$lesson->slug]);
                    }
                }
            }
            if ($course->assessment?->is_published && $course->assessment->due_at) {
                $events->push(['id' => 'assessment-'.$course->assessment->id, 'type' => 'assessment', 'date' => $course->assessment->due_at->toISOString(), 'title' => 'Assessment', 'titleEn' => 'Assessment', 'course' => $course->title, 'courseEn' => $course->title_en, 'url' => '/course-assessment/'.$course->id]);
            }
        }

        LiveSession::query()->whereIn('course_id', $courseIds)->where('status', 'published')->where('starts_at', '>=', now()->subDay())->where('starts_at', '<=', now()->addMonths(3))->with('recording')->get()->each(function (LiveSession $session) use (&$events): void {
            $course = Course::find($session->course_id);
            if (! $course) {
                return;
            }
            $recordingUrl = $session->starts_at->isPast() && $session->recording && $session->recording->status === 'published' && Storage::disk($session->recording->disk)->exists($session->recording->path)
                ? URL::temporarySignedRoute('media.stream', now()->addMinutes(10), ['media' => $session->recording->id]) : null;
            $events->push(['id' => 'session-'.$session->id, 'type' => 'live_session', 'date' => $session->starts_at->toISOString(), 'title' => $session->title, 'titleEn' => $session->title_en, 'course' => $course->title, 'courseEn' => $course->title_en, 'url' => $recordingUrl ?: $session->meeting_url, 'isRecording' => (bool) $recordingUrl]);
        });

        $announcementIds = UserNotification::query()->where('user_id', $student->id)->where('type', 'announcement')->whereNotNull('announcement_id')->pluck('announcement_id');
        $announcements = Announcement::query()->whereIn('id', $announcementIds)->whereNotNull('published_at')->get(['id', 'title', 'title_ar', 'published_at', 'action_url']);
        foreach ($announcements as $announcement) {
            $events->push(['id' => 'announcement-'.$announcement->id, 'type' => 'announcement', 'date' => $announcement->published_at->toISOString(), 'title' => $announcement->title_ar, 'titleEn' => $announcement->title, 'course' => null, 'courseEn' => null, 'url' => $announcement->action_url ?: '/notifications']);
        }

        return response()->json(['data' => $events->sortBy('date')->values()]);
    }
}
