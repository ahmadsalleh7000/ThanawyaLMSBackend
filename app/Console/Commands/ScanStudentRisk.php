<?php

namespace App\Console\Commands;

use App\Services\StudentRiskService;
use Illuminate\Console\Command;

class ScanStudentRisk extends Command
{
    protected $signature = 'students:scan-risk';

    protected $description = 'Detect enrolled students who may need learning intervention';

    public function handle(StudentRiskService $risk): int
    {
        $this->info('Updated '.$risk->scan().' student support case(s).');

        return self::SUCCESS;
    }
}
