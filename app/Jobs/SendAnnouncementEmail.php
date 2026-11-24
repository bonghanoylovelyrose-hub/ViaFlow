<?php

namespace App\Jobs;

use App\Mail\AnnouncementPublished;
use App\Models\AnnouncementDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAnnouncementEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $deliveryId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $delivery = AnnouncementDelivery::query()->with('announcement')->findOrFail($this->deliveryId);
        $recoveringInterruptedAttempt = $delivery->status === 'Sending'
            && $this->job !== null
            && $this->attempts() > 1;
        if (! in_array($delivery->status, ['Queued', 'Retrying'], true) && ! $recoveringInterruptedAttempt) {
            return;
        }

        if ($delivery->announcement?->published_at?->isFuture()) {
            if ($this->job) $this->release(max(1, $delivery->announcement->published_at->timestamp - now()->timestamp));
            return;
        }

        if (! $delivery->announcement || ! $delivery->announcement->isActive()) {
            $delivery->update(['status' => 'Cancelled', 'last_error' => 'Announcement is no longer active.']);
            return;
        }

        $stillEligible = app(\App\Services\AnnouncementAudienceService::class)->recipients($delivery->announcement)
            ->whereKey($delivery->user_id)->where('email', $delivery->recipient_email)->exists();
        if (! $stillEligible) {
            $delivery->update(['status' => 'Cancelled', 'last_error' => 'Recipient is no longer in the announcement audience.']);
            return;
        }

        $delivery->update([
            'status' => 'Sending',
            'attempts' => $delivery->attempts + 1,
            'last_error' => null,
        ]);

        try {
            Mail::to($delivery->recipient_email)->send(new AnnouncementPublished($delivery->announcement));
            $delivery->update(['status' => 'Sent', 'delivered_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'Retrying', 'last_error' => mb_substr($exception->getMessage(), 0, 4000)]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        AnnouncementDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => 'Failed',
            'last_error' => $exception ? mb_substr($exception->getMessage(), 0, 4000) : 'Email delivery failed.',
        ]);
    }
}
