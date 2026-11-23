<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssignmentWorkflowService
{
    public function reviewVersion(AssignmentSubmission $submission): string
    {
        return hash('sha256', json_encode($submission->only(['id', 'body', 'file_sha256', 'submitted_at', 'status', 'score', 'feedback', 'graded_by', 'graded_at'])));
    }

    public function serialize(Assignment $assignment, ?AssignmentSubmission $submission = null): array
    {
        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'titleEn' => $assignment->title_en,
            'description' => $assignment->description,
            'descriptionEn' => $assignment->description_en,
            'dueDate' => $assignment->due_at?->toISOString(),
            'isPublished' => $assignment->is_published,
            'position' => $assignment->position,
            'status' => $submission?->status ?? ($assignment->due_at?->isPast() ? 'overdue' : 'ready'),
            'canSubmit' => $assignment->is_published && $submission?->status !== 'graded',
            'submission' => $submission ? [
                'id' => $submission->id, 'body' => $submission->body,
                'fileName' => $submission->file_original_name,
                'fileMime' => $submission->file_mime, 'fileSize' => $submission->file_size,
                'submittedAt' => $submission->submitted_at?->toISOString(),
                'score' => $submission->score, 'feedback' => $submission->feedback,
                'gradedAt' => $submission->graded_at?->toISOString(),
            ] : null,
        ];
    }

    public function submit(Request $request, Assignment $assignment, array $data): AssignmentSubmission
    {
        $newPath = null;
        $oldPath = null;
        try {
            $submission = DB::transaction(function () use ($request, $assignment, $data, &$newPath, &$oldPath) {
                $assignment = Assignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
                abort_unless($assignment->is_published, 404);
                $submission = $assignment->submissions()->where('user_id', $request->user()->id)->lockForUpdate()->first();
                if ($submission?->status === 'graded') {
                    throw ValidationException::withMessages(['body' => __('Graded work cannot be changed. Ask your teacher to return it for revision.')]);
                }
                $file = $request->file('file');
                $body = trim($data['body'] ?? '');
                if ($body === '' && ! $file && ! $submission?->file_path) {
                    throw ValidationException::withMessages(['body' => __('Write an answer or attach a file.')]);
                }
                $values = [
                    'body' => $body === '' ? null : $body,
                    'status' => $submission?->status === 'returned' ? 'resubmitted' : ($assignment->due_at?->isPast() ? 'late' : 'submitted'),
                    'submitted_at' => now(), 'score' => null, 'feedback' => null, 'graded_by' => null, 'graded_at' => null,
                ];
                if ($file) {
                    $newPath = $file->store('assignment-submissions/'.now()->format('Y/m'), 'local');
                    if (! $newPath) {
                        throw new \RuntimeException('Unable to store assignment attachment.');
                    }
                    $oldPath = $submission?->file_path;
                    $values += [
                        'file_path' => $newPath, 'file_original_name' => $file->getClientOriginalName(),
                        'file_mime' => $file->getMimeType(), 'file_size' => $file->getSize(),
                        'file_sha256' => hash_file('sha256', Storage::disk('local')->path($newPath)),
                    ];
                }

                return $assignment->submissions()->updateOrCreate(['user_id' => $request->user()->id], $values);
            });
        } catch (\Throwable $error) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $error;
        }
        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $submission;
    }

    public function review(Request $request, AssignmentSubmission $submission, bool $returnForRevision, string $actor): AssignmentSubmission
    {
        $data = $request->validate([
            'score' => $returnForRevision ? ['nullable'] : ['required', 'integer', 'min:0', 'max:100'],
            'feedback' => [$returnForRevision ? 'required' : 'nullable', 'string', 'max:5000'],
            'review_version' => ['sometimes', 'string', 'size:64'],
        ]);

        return DB::transaction(function () use ($request, $submission, $data, $returnForRevision, $actor) {
            Assignment::whereKey($submission->assignment_id)->lockForUpdate()->firstOrFail();
            $submission = AssignmentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            // Reject a review of work replaced since the reviewer opened the page.
            if (($request->filled('review_version') && ! hash_equals($this->reviewVersion($submission), $request->string('review_version')->toString()))
                || ($request->filled('submitted_at') && $request->string('submitted_at')->toString() !== $submission->submitted_at?->toISOString())) {
                throw ValidationException::withMessages(['score' => __('This submission has changed. Refresh it before reviewing.')]);
            }
            $before = $submission->attributesToArray();
            $submission->update([
                'status' => $returnForRevision ? 'returned' : 'graded',
                'score' => $returnForRevision ? null : $data['score'], 'feedback' => $data['feedback'] ?? null,
                'graded_by' => $request->user()->id, 'graded_at' => now(),
            ]);
            app(AuditService::class)->record($request, $actor.'.assignment_submission.'.($returnForRevision ? 'returned' : 'graded'), $submission, $before);
            $submission->loadMissing(['assignment.lesson', 'user']);
            $submission->user?->userNotifications()->create([
                'type' => $returnForRevision ? 'assignment_returned' : 'assignment_graded',
                'title' => $returnForRevision ? 'Assignment returned for revision' : 'Assignment graded',
                'title_ar' => $returnForRevision ? 'تمت إعادة المهمة للمراجعة' : 'تم تصحيح المهمة',
                'message' => $submission->assignment->title, 'message_ar' => $submission->assignment->title,
                'action_url' => '/course-player/'.$submission->assignment->lesson->course_id.'?lesson='.$submission->assignment->lesson->slug.'&tab=assignments',
            ]);

            return $submission;
        });
    }

    public function download(AssignmentSubmission $submission): BinaryFileResponse
    {
        abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);

        return response()->download(Storage::disk('local')->path($submission->file_path), $submission->file_original_name, [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
