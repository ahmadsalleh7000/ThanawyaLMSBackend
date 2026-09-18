<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('announcements:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('courses:run-scheduled-actions')->everyMinute()->withoutOverlapping();
Schedule::command('students:scan-risk')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('engagement:send-notifications')->hourly()->withoutOverlapping();
Schedule::command('sessions:send-reminders')->hourly()->withoutOverlapping();
Schedule::command('payments:expire-manual-orders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('payments:monitor-reconciliation')->hourly()->withoutOverlapping();
Schedule::command('payments:monitor-reconciliation --summary')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('system:backup-database')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('system:verify-backups')->weeklyOn(1, '04:00')->withoutOverlapping();
Schedule::command('system:enforce-retention')->dailyAt('04:30')->withoutOverlapping();
Schedule::command('privacy:process-deletions')->dailyAt('05:00')->withoutOverlapping();
Schedule::command('privacy:prune-legal-records')->dailyAt('05:30')->withoutOverlapping();
Schedule::command('guardians:expire-invitations')->hourly()->withoutOverlapping();
Schedule::command('ops:monitor-health')->everyMinute()->withoutOverlapping();
