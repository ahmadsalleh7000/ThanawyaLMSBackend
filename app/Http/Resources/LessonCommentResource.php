<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $author = $this->user;

        return [
            'id' => $this->id,
            'content' => $this->content,
            'author' => [
                'id' => $author?->id,
                'name' => $author?->name ?? 'Former student',
                'role' => $author?->isAdmin() ? 'admin' : ($author?->role === 'teacher' ? 'teacher' : 'student'),
            ],
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'edited' => $this->edited_at !== null,
            'parentId' => $this->parent_id,
            'canEdit' => $viewer?->can('update', $this->resource) ?? false,
            'canDelete' => $viewer?->can('delete', $this->resource) ?? false,
            'canReply' => $this->course_id !== null || (bool) $this->lesson?->comments_enabled,
            'replies' => self::collection($this->whenLoaded('replies')),
        ];
    }
}
