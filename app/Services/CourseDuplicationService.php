<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Facades\DB;

class CourseDuplicationService
{
    public function duplicate(Course $source, string $newId, string $title, string $titleEn): Course
    {
        return DB::transaction(function () use ($source, $newId, $title, $titleEn): Course {
            $source->load(['units', 'lessons.mediaAssets', 'lessons.sections', 'lessons.assignments', 'assessment.questions']);
            $attributes = collect($source->attributesToArray())->except(['id', 'source_course_id', 'title', 'title_en', 'created_at', 'updated_at', 'archived_at', 'is_published', 'rating', 'enrolled_count'])->all();
            $copy = Course::create($attributes + ['id' => $newId, 'source_course_id' => $source->id, 'title' => $title, 'title_en' => $titleEn, 'is_published' => false, 'rating' => 0, 'enrolled_count' => 0]);
            $unitMap = [];
            foreach ($source->units as $unit) {
                $unitCopy = $copy->units()->create(collect($unit->attributesToArray())->only(['slug', 'title_ar', 'title_en', 'position', 'status', 'available_from', 'available_until'])->all());
                $unitMap[$unit->id] = $unitCopy->id;
            }
            foreach ($source->lessons as $lesson) {
                $lessonAttributes = collect($lesson->attributesToArray())->only([
                    'slug', 'title', 'title_en', 'duration_minutes', 'position', 'is_preview', 'video_url',
                    'content_pdf_enabled', 'content_pdf_url', 'content_pdf_title', 'content_pdf_description',
                    'workbook_enabled', 'allow_print', 'allow_pdf_download', 'include_workbook_cover',
                    'include_workbook_info', 'include_workbook_toc', 'include_workbook_answer_key',
                    'objectives', 'key_points', 'summary', 'completion_rules', 'transcript', 'transcript_en', 'captions',
                ])->all();
                if ($lesson->unit_id && isset($unitMap[$lesson->unit_id])) {
                    $lessonAttributes['unit_id'] = $unitMap[$lesson->unit_id];
                }
                $lessonCopy = $copy->lessons()->create($lessonAttributes);
                foreach ($lesson->mediaAssets as $asset) {
                    $assetCopy = $lessonCopy->mediaAssets()->create(collect($asset->attributesToArray())->only(['media_library_item_id', 'kind', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes'])->all());
                    if ((int) $lesson->content_pdf_media_id === (int) $asset->id) {
                        $lessonCopy->update(['content_pdf_media_id' => $assetCopy->id]);
                    }
                }
                foreach ($lesson->sections as $section) {
                    $lessonCopy->sections()->create(collect($section->attributesToArray())->only(['type', 'title', 'content', 'sort_order', 'settings'])->all());
                }
                foreach ($lesson->assignments as $assignment) {
                    $lessonCopy->assignments()->create(collect($assignment->attributesToArray())->only(['title', 'title_en', 'description', 'description_en', 'due_at', 'position'])->all() + ['is_published' => false]);
                }
            }
            if ($source->assessment) {
                $assessment = $copy->assessment()->create(collect($source->assessment->attributesToArray())->only(['duration_minutes', 'passing_score', 'randomize_questions', 'question_limit'])->all() + ['is_published' => false]);
                foreach ($source->assessment->questions as $question) {
                    $assessment->questions()->create(collect($question->attributesToArray())->only(['question_bank_question_id', 'type', 'difficulty', 'topic', 'topic_ar', 'prompt', 'prompt_ar', 'options', 'options_ar', 'correct_option', 'explanation', 'explanation_ar', 'position'])->all());
                }
            }

            return $copy;
        });
    }
}
