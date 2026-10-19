<?php

namespace App\Console\Commands;

use App\Services\ProductionAdminService;
use App\Services\ProductionReferenceDataService;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class SetupProduction extends Command
{
    protected $signature = 'production:setup';

    protected $description = 'Create the first super administrator and missing production reference data';

    public function handle(ProductionAdminService $admins, ProductionReferenceDataService $referenceData): int
    {
        if (! app()->environment('production')) {
            $this->error('Production setup requires APP_ENV=production. Use local seeders for development.');

            return self::FAILURE;
        }

        try {
            foreach ([
                'users', 'audit_logs', 'subscription_plans', 'notification_templates',
                'academic_years', 'academic_grades', 'academic_terms', 'subjects',
                'academic_subjects', 'subscription_plan_academic_term',
            ] as $table) {
                if (! Schema::hasTable($table)) {
                    $this->error('Production setup requires the current schema. Run php artisan migrate --force first.');

                    return self::FAILURE;
                }
            }

            $result = Cache::lock('production-setup', 300)->get(fn () => DB::transaction(function () use ($admins, $referenceData): array {
                $createdAdmin = $admins->ensure();
                $created = ['super_admin' => (int) $createdAdmin, ...$referenceData->ensure()];
                $totals = [];
                foreach ($created as $type => $count) {
                    $totals[$type] = $type === 'super_admin' ? $admins->superAdminCount() : DB::table($type)->count();
                }

                return ['created' => $created, 'totals' => $totals];
            }));

            if ($result === false) {
                $this->error('Another production setup is running. Retry after it finishes.');

                return self::FAILURE;
            }
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Production setup could not complete. Check migrations and database/cache connectivity, then retry.');

            return self::FAILURE;
        }

        $this->table(['Record type', 'Created', 'Total'], collect($result['created'])->map(fn (int $count, string $type) => [$type, $count, $result['totals'][$type]])->values()->all());
        $this->info($result['created']['super_admin'] ? 'Initial super administrator created.' : 'Existing super administrator preserved.');
        $this->info('Production setup complete. Existing records were preserved; no demo content was created.');

        return self::SUCCESS;
    }
}
