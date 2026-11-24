<?php

namespace App\Services;

use App\Jobs\SendAnnouncementEmail;
use App\Models\Announcement;
use Illuminate\Support\Facades\DB;

class AnnouncementNotificationService
{
    public function queueForPublishedAnnouncement(Announcement $announcement): int
    {
        if (! $announcement->isActive()) return 0;
        $queuedIds = [];
        $recipientEmails = $this->audience()->recipients($announcement)->pluck('email')->filter()->unique()->values()->all();
        DB::table('announcement_deliveries')
            ->where('announcement_id', $announcement->id)
            ->whereIn('status', ['Queued', 'Retrying'])
            ->whereNotIn('recipient_email', $recipientEmails ?: [''])
            ->update(['status' => 'Cancelled', 'updated_at' => now()]);

        $this->audience()->recipients($announcement)->select(['id', 'email'])->chunkById(200, function ($users) use ($announcement, &$queuedIds) {
            foreach ($users as $user) {
                $inserted = DB::table('announcement_deliveries')->insertOrIgnore([
                    'announcement_id' => $announcement->id,
                    'user_id' => $user->id,
                    'recipient_email' => $user->email,
                    'status' => 'Queued',
                    'attempts' => 0,
                    'queued_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $delivery = DB::table('announcement_deliveries')
                        ->where('announcement_id', $announcement->id)
                        ->where('recipient_email', $user->email)
                        ->first(['id', 'status']);

                if ($delivery && in_array($delivery->status, ['Queued', 'Retrying'], true)) {
                    $queuedIds[] = (int) $delivery->id;
                }
            }
        });

        foreach ($queuedIds as $id) {
            SendAnnouncementEmail::dispatch($id)->onConnection('database')->onQueue('announcements');
        }

        return count($queuedIds);
    }

    public function queueIfDue(Announcement $announcement): void
    {
        DB::transaction(function () use ($announcement) {
            $current = Announcement::query()->whereKey($announcement->id)->lockForUpdate()->first();
            if (! $current || $current->notifications_queued_at || ! $current->isActive()) return;
            $this->queueForPublishedAnnouncement($current);
            $current->forceFill(['notifications_queued_at' => now()])->save();
        });
    }

    private function audience(): AnnouncementAudienceService
    {
        return app(AnnouncementAudienceService::class);
    }
}
