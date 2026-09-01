<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\AnnouncementDeliveryService;
use Illuminate\Console\Command;

class DispatchScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:dispatch';

    protected $description = 'Deliver due scheduled announcements to their target audiences';

    public function handle(AnnouncementDeliveryService $delivery): int
    {
        $campaigns = 0;
        $recipients = 0;
        Announcement::due()->orderBy('id')->eachById(function (Announcement $announcement) use ($delivery, &$campaigns, &$recipients): void {
            $recipients += $delivery->deliver($announcement);
            $campaigns++;
        });
        $this->info("Published {$campaigns} announcement(s) to {$recipients} recipient(s).");

        return self::SUCCESS;
    }
}
