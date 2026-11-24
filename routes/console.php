<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily overdue sweep: reminders plus disconnection notices, de-duplicated
// per bill so repeated runs never re-send the same notice.
Schedule::command('billing:monitor-overdue')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Artisan::command('announcements:dispatch-due', function () {
    \App\Models\Announcement::query()->active()->whereNull('notifications_queued_at')
        ->chunkById(100, function ($announcements) {
            foreach ($announcements as $announcement) {
                app(\App\Services\AnnouncementNotificationService::class)->queueIfDue($announcement);
            }
        });
})->purpose('Queue notifications for scheduled announcements that are now active');

Schedule::command('announcements:dispatch-due')->everyMinute()->withoutOverlapping();
