<?php

namespace App\Http\Resources;

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
        $canAccessContent = $this->is_preview
            || (bool) $this->getAttribute('content_access_granted')
            || $request->user()?->isAdmin();
        $assets = $canAccessContent && $this->relationLoaded('mediaAssets')
            ? $this->mediaAssets->filter(fn ($asset) => ! $asset->libraryItem || ($asset->libraryItem->processing_status === 'ready' && ! $asset->libraryItem->archived_at))
            : collect();

        $pdfAsset = $canAccessContent && $this->content_pdf_media_id
            ? $assets->firstWhere('id', $this->content_pdf_media_id)
            : null;
        $contentPdfUrl = $pdfAsset ? null : ($canAccessContent ? $this->content_pdf_url : null);
        $contentPdfTitle = $pdfAsset
            ? pathinfo($pdfAsset->original_name, PATHINFO_FILENAME)
            : $this->content_pdf_title;
        $workspace = $canAccessContent && $request->user()
            ? $request->user()->lessonWorkspaceStates()->where('lesson_id', $this->id)->first()
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

        return ['id' => $this->slug, 'databaseId' => $this->id, 'title' => $this->title, 'titleEn' => $this->title_en, 'duration' => $this->duration_minutes.'m', 'durationMinutes' => (int) $this->duration_minutes, 'preview' => $this->is_preview, 'videoUrl' => $canAccessContent ? $this->video_url : null, 'assignments' => $assignments->map(fn ($assignment) => [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'titleEn' => $assignment->title_en,
            'description' => $assignment->description,
            'descriptionEn' => $assignment->description_en,
            'dueDate' => $assignment->due_at?->toISOString(),
            'status' => 'ready',
        ])->values(), 'contentPdf' => [
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
            'practicePassed' => (bool) $workspace?->practice_passed_at,
            'playbackSeconds' => (int) ($workspace?->playback_seconds ?? 0),
        ], 'trainings' => $trainings->map(fn ($training) => [
            'id' => $training->id,
            'type' => $training->type,
            'title' => $training->title_ar,
            'titleEn' => $training->title_en,
            'instructions' => $training->instructions_ar,
            'instructionsEn' => $training->instructions_en,
            'isRequired' => (bool) $training->is_required,
            'passingScore' => $training->passing_score,
            'questionsCount' => $training->question_bank_quiz_id && $training->relationLoaded('quiz') && $training->quiz
                ? $training->quiz->questions->count()
                : ($training->questions_count ?? ($training->relationLoaded('questions') ? $training->questions->count() : 0)),
        ])->values(), 'media' => $assets->map(fn ($asset) => ['id' => $asset->id, 'kind' => $asset->kind, 'name' => $asset->original_name, 'mimeType' => $asset->mime_type, 'size' => $asset->size_bytes])->values(), 'transcript' => $canAccessContent ? $this->transcript : null, 'transcriptEn' => $canAccessContent ? $this->transcript_en : null, 'captions' => $canAccessContent ? ($this->captions ?: []) : []];
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
