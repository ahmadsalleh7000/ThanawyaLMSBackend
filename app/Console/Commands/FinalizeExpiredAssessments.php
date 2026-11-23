<?php

namespace App\Console\Commands;

use App\Models\AssessmentDraft;
use App\Models\TrainingDraft;
use App\Services\ExamSubmissionService;
use Illuminate\Console\Command;
use Throwable;

class FinalizeExpiredAssessments extends Command
{
    protected $signature = 'assessments:finalize-expired';

    protected $description = 'Grade saved exam and lesson quiz answers after their server deadline';

    public function handle(ExamSubmissionService $submissions): int
    {
        $count = 0;
        $failures = 0;
        foreach ([AssessmentDraft::class => 'assessment', TrainingDraft::class => 'training'] as $model => $relation) {
            $model::where('expires_at', '<=', now())->with($relation)->chunkById(100, function ($drafts) use ($submissions, $relation, &$count, &$failures) {
                foreach ($drafts as $draft) {
                    try {
                        if ($draft->$relation && $submissions->submit($draft->$relation, $draft->user_id ?? $draft->student_id, draftId: $draft->id, expiredOnly: true)) {
                            $count++;
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->error('Could not finalize '.$relation.' draft '.$draft->id.'.');
                        $failures++;
                    }
                }
            });
        }
        $this->info('Finalized '.$count.' expired assessments.');

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
