<?php

namespace App\Console\Commands;

use App\Models\AccountDeletionRequest;
use App\Services\AccountAnonymizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class ProcessAccountDeletions extends Command
{
    protected $signature = 'privacy:process-deletions {--request= : Process one eligible request ID}';

    protected $description = 'Securely anonymize approved account deletion requests after their cooling-off period';

    public function handle(AccountAnonymizationService $anonymizer): int
    {
        $query = AccountDeletionRequest::where('status', 'approved')->where('scheduled_for', '<=', now());
        if ($id = $this->option('request')) {
            $query->whereKey($id);
        }
        $processed = 0;
        $failed = 0;
        $query->orderBy('id')->each(function (AccountDeletionRequest $request) use ($anonymizer, &$processed, &$failed): void {
            try {
                $anonymizer->process($request);
                $processed++;
            } catch (Throwable $exception) {
                $request->update(['status' => 'failed', 'processing_error' => Str::limit($exception->getMessage(), 1000)]);
                report($exception);
                $failed++;
            }
        });
        $this->info("Processed {$processed}; failed {$failed}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
