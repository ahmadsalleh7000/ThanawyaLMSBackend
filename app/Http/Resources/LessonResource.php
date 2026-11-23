<?php

namespace App\Http\Resources;

use App\Models\AssignmentSubmission;
use App\Services\AssignmentWorkflowService;
use App\Services\ExamDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class LessonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $student = $request->user('sanctum');
        $canAccessContent = ($this->is_preview && ! $this->getAttribute('lock_reason'))
            || (bool) $this->getAttribute('content_access_granted')
            || $student?->isAdmin();
        $metadata = [
            'id' => $this->slug, 'databaseId' => $this->id, 'title' => $this->title, 'titleEn' => $this->title_en,
            'duration' => $this->duration_minutes.'m', 'durationMinutes' => (int) $this->duration_minutes,
            'preview' => $this->is_preview, 'locked' => (bool) $this->getAttribute('lock_reason'),
            'lockReason' => $this->getAttribute('lock_reason'),
            'availableFrom' => ($this->available_from ?? $this->unit?->available_from)?->toISOString(),
            'availableUntil' => ($this->available_until ?? $this->unit?->available_until)?->toISOString(),
            'unitId' => $this->unit_id, 'unitPosition' => $this->unit?->position, 'unitTitle' => $this->unit?->title_ar, 'unitTitleEn' => $this->unit?->title_en,
        ];
        if ($request->query('view') === 'catalog') {
            return $metadata;
        }
        $assets = $canAccessContent && $this->relationLoaded('mediaAssets')
            ? $this->mediaAssets->filter(fn ($asset) => $asset->status === 'published' && ! $asset->available_from?->isFuture() && ! $asset->available_until?->isPast() && (! $asset->libraryItem || ($asset->libraryItem->processing_status === 'ready' && ! $asset->libraryItem->archived_at)))
            : collect();

        $pdfAsset = $canAccessContent && $this->content_pdf_media_id
            ? $assets->firstWhere('id', $this->content_pdf_media_id)
            : null;
        $contentPdfUrl = $pdfAsset ? null : ($canAccessContent ? $this->content_pdf_url : null);
        $contentPdfTitle = $pdfAsset
            ? pathinfo($pdfAsset->original_name, PATHINFO_FILENAME)
            : $this->content_pdf_title;
        $workspace = $canAccessContent && $student
            ? $student->lessonWorkspaceStates()->where('lesson_id', $this->id)->first()
            : null;
        $trainings = $canAccessContent && $this->relationLoaded('trainings')
            ? $this->trainings->filter(fn ($training) => $training->status === 'published')
            : collect();
        $assignments = $canAccessContent && $this->relationLoaded('assignments')
            ? $this->assignments->filter(fn ($assignment) => $assignment->is_published)
            : collect();
        $sections = $canAccessContent && $this->relationLoaded('sections')
            ? $this->sections->filter(fn ($section) => $section->visibleOnWeb())
            : collect();

        $studentId = $student?->id;
        $assignmentSubmissions = $studentId && $assignments->isNotEmpty()
            ? AssignmentSubmission::where('user_id', $studentId)->whereIn('assignment_id', $assignments->pluck('id'))->get()->keyBy('assignment_id')
            : collect();

        return $metadata + ['id' => $this->slug, 'databaseId' => $this->id, 'title' => $this->title, 'titleEn' => $this->title_en, 'duration' => $this->duration_minutes.'m', 'durationMinutes' => (int) $this->duration_minutes, 'preview' => $this->is_preview, 'videoUrl' => $canAccessContent ? $this->video_url : null, 'assignments' => $assignments->sortBy('position')->map(fn ($assignment) => app(AssignmentWorkflowService::class)->serialize($assignment, $assignmentSubmissions->get($assignment->id)))->values(), 'contentPdf' => [
            'enabled' => $canAccessContent && $this->content_pdf_enabled && ($pdfAsset || filled($contentPdfUrl)),
            'mediaId' => $pdfAsset?->id,
            'url' => $contentPdfUrl,
            'title' => $contentPdfTitle,
            'description' => $this->content_pdf_description,
        ], 'sections' => $sections->map(fn ($section) => [
            'id' => $section->id,
            'type' => $section->type,
            'title' => $section->title,
            'content' => $this->sectionContent($section, $assets),
            'settings' => $section->settingsWithDefaults(),
            'anchor' => $section->anchor(),
        ])->values(), 'objectives' => $canAccessContent ? $this->objectives : null, 'keyPoints' => $canAccessContent ? $this->key_points : null, 'summary' => $canAccessContent ? $this->summary : null, 'completionRules' => $this->completion_rules ?: [], 'learningState' => [
            'contentOpened' => (bool) $workspace?->content_opened_at,
            'videoConfirmed' => (bool) $workspace?->video_confirmed_at,
            'practicePassed' => (bool) $workspace?->practice_passed_at,
            'playbackSeconds' => (int) ($workspace?->playback_seconds ?? 0),
        ], 'trainings' => $trainings->map(function ($training) use ($studentId) {
            $bestAttempt = $studentId
                ? $training->attempts()
                    ->where('student_id', $studentId)
                    ->whereNotNull('submitted_at')
                    ->orderByDesc('score')
                    ->orderByDesc('submitted_at')
                    ->first()
                : null;

            return [
                'id' => $training->id,
                'type' => $training->type,
                'title' => $training->title_ar,
                'titleEn' => $training->title_en,
                'instructions' => $training->instructions_ar,
                'instructionsEn' => $training->instructions_en,
                'isRequired' => (bool) $training->is_required,
                'passingScore' => $training->passing_score,
                'policy' => $studentId ? app(ExamDeliveryService::class)->state($training, $studentId) : null,
                'questionsCount' => $training->question_limit ? min($training->questionSet()->count(), $training->question_limit) : $training->questionSet()->count(),
                'bestAttempt' => $bestAttempt ? [
                    'id' => $bestAttempt->id,
                    'score' => (float) $bestAttempt->score,
                    'status' => $bestAttempt->status,
                    'passed' => $bestAttempt->status === 'passed',
                    'submittedAt' => $bestAttempt->submitted_at?->toISOString(),
                ] : null,
            ];
        })->values(), 'media' => $assets->map(fn ($asset) => ['id' => $asset->id, 'kind' => $asset->kind, 'name' => $asset->original_name, 'mimeType' => $asset->mime_type, 'size' => $asset->size_bytes, 'offlineAllowed' => (bool) ($this->is_preview && $this->allow_pdf_download && $asset->kind === 'resource' && $asset->mime_type === 'application/pdf'), 'url' => $this->is_preview && $this->allow_pdf_download && $asset->kind === 'resource' && $asset->mime_type === 'application/pdf' ? url('/api/media/'.$asset->id.'/offline') : null])->values(), 'transcript' => $canAccessContent ? $this->transcript : null, 'transcriptEn' => $canAccessContent ? $this->transcript_en : null, 'captionsLanguage' => preg_match('/\p{Arabic}/u', json_encode($this->captions ?: [], JSON_UNESCAPED_UNICODE)) ? 'ar' : 'en', 'captions' => $canAccessContent ? ($this->captions ?: []) : []];
    }

    private function sectionContent($section, $assets): array
    {
        $content = $section->content ?: [];
        $imageMediaId = data_get($content, 'image.media_asset_id');
        if ($section->type === 'image' && $imageMediaId) {
            $asset = $assets->firstWhere('id', $imageMediaId);
            if ($asset && str_starts_with((string) $asset->mime_type, 'image/')) {
                data_set($content, 'image.url', URL::temporarySignedRoute('media.stream', now()->addMinutes(15), [
                    'media' => $asset->id,
                    'download' => 0,
                ]));
            }
        }

        return $content;
    }
}
