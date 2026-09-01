<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'titleEn' => $this->title_en, 'summary' => $this->summary, 'summaryEn' => $this->summary_en, 'description' => $this->description, 'descriptionEn' => $this->description_en, 'imageUrl' => $this->image_url, 'category' => $this->category, 'scope' => $this->scope?->value ?? 'academic', 'grade' => $this->grade, 'semester' => $this->semester, 'unit' => $this->unit, 'trackType' => $this->track_type, 'level' => $this->level, 'duration' => sprintf('%dh %02dm', intdiv($this->duration_minutes, 60), $this->duration_minutes % 60), 'price' => (float) $this->price, 'isFree' => (float) $this->price === 0.0, 'rating' => (float) $this->rating, 'enrolled' => $this->enrolled_count, 'instructor' => $this->instructor, 'instructorEn' => $this->instructor_en, 'lessons' => LessonResource::collection($this->whenLoaded('lessons'))];
    }
}
