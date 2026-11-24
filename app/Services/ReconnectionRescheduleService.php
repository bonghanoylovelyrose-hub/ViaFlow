<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\ConsumerReconnection;
use App\Models\InstallationRescheduleRequest;
use App\Models\User;
use App\Notifications\InstallationRescheduleRequested;
use App\Support\ReconnectionSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ReconnectionRescheduleService
{
    public function assertCurrent(Consumer $consumer, ?ConsumerReconnection $reconnection): Carbon
    {
        abort_unless($this->isCurrent($consumer, $reconnection), 403, 'This reconnection schedule is no longer open for rescheduling.');

        return $reconnection->scheduledAt($consumer);
    }

    public function isCurrent(Consumer $consumer, ?ConsumerReconnection $reconnection): bool
    {
        return $consumer->status === 'Active' && $consumer->reconnection_pending
            && $reconnection && ! $reconnection->completed_at
            && $reconnection->consumer_id === $consumer->id
            && (int) $consumer->reconnections()->max('id') === $reconnection->id;
    }

    public function submit(ConsumerReconnection $reconnection, array $values): InstallationRescheduleRequest
    {
        return DB::transaction(function () use ($reconnection, $values): InstallationRescheduleRequest {
            $consumer = Consumer::query()->lockForUpdate()->findOrFail($reconnection->consumer_id);
            $scheduledAt = $this->assertCurrent($consumer, $reconnection);
            app(InstallationRescheduleService::class)->assertAvailable($consumer, $reconnection);
            if (InstallationRescheduleRequest::query()->where('consumer_reconnection_id', $reconnection->id)->pending()->exists()) {
                throw ValidationException::withMessages(['reason' => 'A reschedule request is already pending administrator approval.']);
            }
            if (ReconnectionSchedule::parse($values['preferred_date'].' '.$values['preferred_time'])->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['preferred_time' => 'Choose a future reconnection date and time.']);
            }
            $current = $scheduledAt->copy()->setTimezone(ReconnectionSchedule::TIMEZONE);
            $reschedule = InstallationRescheduleRequest::query()->create([
                'reschedule_type' => InstallationRescheduleRequest::TYPE_RECONNECTION,
                'consumer_reconnection_id' => $reconnection->id,
                'consumer_id' => $consumer->id,
                'status' => InstallationRescheduleRequest::STATUS_PENDING,
                'original_installation_date' => $current->toDateString(),
                'original_installation_time' => $current->format('H:i:s'),
                'preferred_installation_date' => $values['preferred_date'],
                'preferred_installation_time' => $values['preferred_time'],
                'reason' => $values['reason'],
            ]);
            Notification::send(User::query()->where('role', 'admin')->get(), new InstallationRescheduleRequested($reschedule));

            return $reschedule;
        });
    }

    public function review(InstallationRescheduleRequest $reschedule, User $admin, bool $approve, array $values): bool
    {
        abort_unless($admin->isAdmin(), 403);
        if (! $approve) {
            throw ValidationException::withMessages(['reschedule' => 'Only Assign New Schedule is available.']);
        }

        return app(InstallationRescheduleService::class)->assign($reschedule, $admin, $values);
    }
}
