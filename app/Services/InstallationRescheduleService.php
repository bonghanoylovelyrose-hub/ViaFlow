<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\ConsumerReconnection;
use App\Models\InstallationRescheduleRequest;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\InstallationScheduleNotification;
use App\Support\ReconnectionSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InstallationRescheduleService
{
    public const LIMIT = 3;

    public const REMINDER = 'Please be reminded that you are allowed a maximum of 3 installation reschedules.';

    public const FINAL_WARNING = 'IMPORTANT: This is your final reschedule. No further rescheduling will be allowed. Please ensure that someone is available at home on the scheduled installation date.';

    public function usage(Consumer $consumer, ?ConsumerReconnection $reconnection = null): array
    {
        $used = $consumer->installationRescheduleRequests()
            ->when($reconnection, fn ($query) => $query->where('consumer_reconnection_id', $reconnection->id))
            ->where(function ($query) {
                $query->whereNotNull('assigned_at')->orWhere(function ($query) {
                    $query->whereIn('status', [InstallationRescheduleRequest::STATUS_APPROVED, InstallationRescheduleRequest::STATUS_ASSIGNED])->whereNotNull('new_installation_date');
                });
            })->count();

        return ['used' => $used, 'limit' => self::LIMIT, 'remaining' => max(0, self::LIMIT - $used), 'available' => $used < self::LIMIT];
    }

    public function assertAvailable(Consumer $consumer, ?ConsumerReconnection $reconnection = null): void
    {
        if ($this->usage($consumer, $reconnection)['available'] === false) {
            $type = $reconnection ? 'reconnection' : 'installation';
            throw ValidationException::withMessages(['reschedule' => "You have used all 3 {$type} reschedules. No further rescheduling is allowed."]);
        }
    }

    public function assign(InstallationRescheduleRequest $request, User $admin, array $values): bool
    {
        abort_unless($admin->isAdmin(), 403);
        DB::transaction(function () use ($request, $admin, $values): void {
            $consumer = Consumer::query()->lockForUpdate()->findOrFail($request->consumer_id);
            $locked = InstallationRescheduleRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->isPending() === false || $locked->assigned_at !== null) {
                throw ValidationException::withMessages(['reschedule' => 'A new schedule has already been assigned, or this request is no longer pending.']);
            }
            $reconnection = $locked->isReconnection() ? $locked->reconnection : null;
            $currentReconnection = $locked->isReconnection()
                ? app(ReconnectionRescheduleService::class)->assertCurrent($consumer, $reconnection)
                : null;
            $this->assertAvailable($consumer, $reconnection);
            $timezone = match (true) {
                $locked->isLoan() => Loan::INSTALLATION_TIMEZONE,
                $locked->isReconnection() => ReconnectionSchedule::TIMEZONE,
                default => config('app.timezone'),
            };
            $schedule = Carbon::parse($values['new_installation_date'].' '.$values['new_installation_time'], $timezone);
            if ($schedule->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['new_installation_time' => 'Choose a future installation date and time.']);
            }
            $currentReconnection = $currentReconnection?->copy()->setTimezone($timezone);
            $loan = $locked->isLoan() ? $locked->loan()->lockForUpdate()->firstOrFail() : null;
            if ($loan?->installation_status === 'Completed') {
                throw ValidationException::withMessages(['reschedule' => 'This installation has already been completed.']);
            }
            $loanSchedule = $loan?->installationSchedule();
            if ($loan && (! $loan->isApprovedForInstallation() || ! $loanSchedule)) {
                throw ValidationException::withMessages(['reschedule' => 'An approved loan with a saved installation date and time is required.']);
            }
            $currentSchedule = $currentReconnection ?? $loanSchedule;
            $currentDate = ($currentSchedule ?? $consumer->installation_date)?->toDateString();
            $currentTime = $currentSchedule?->format('H:i:s') ?? $consumer->installation_time;
            if ($currentDate && $currentTime && $schedule->equalTo(Carbon::parse($currentDate.' '.$currentTime, $timezone))) {
                throw ValidationException::withMessages(['new_installation_time' => 'The new schedule must differ from the current installation date and time.']);
            }
            $number = max($this->usage($consumer)['used'], (int) $consumer->installationRescheduleRequests()->max('assignment_number')) + 1;
            if ($loan) {
                $loan->update([
                    'installation_date' => $schedule->toDateString(), 'installation_time' => $schedule->format('H:i:s'),
                    'installation_notes' => $values['admin_notes'] ?? $loan->installation_notes,
                    'installation_status' => 'Scheduled', 'review_status' => 'Installation Scheduled',
                ]);
            } elseif ($locked->isReconnection()) {
                $consumer->update(['reconnection_scheduled_at' => $schedule->copy()->utc()]);
            } else {
                $consumer->update(['installation_date' => $schedule->toDateString(), 'installation_time' => $schedule->format('H:i:s')]);
            }
            $locked->update([
                'status' => InstallationRescheduleRequest::STATUS_ASSIGNED,
                'new_installation_date' => $schedule->toDateString(), 'new_installation_time' => $schedule->format('H:i:s'),
                'admin_notes' => $values['admin_notes'] ?? null,
                'reviewed_by_user_id' => $admin->id, 'reviewed_at' => now(), 'assigned_at' => now(), 'assignment_number' => $number,
            ]);
        });
        $request->refresh()->load('consumer');
        $sent = true;
        DB::afterCommit(function () use ($request, &$sent): void {
            $sent = $this->sendAssignmentEmail($request);
            try {
                $request->consumer->user?->notify(new InstallationScheduleNotification(
                    'New Installation Schedule Assigned', 'Your new installation schedule is '.$request->formattedNewSchedule().'. '.self::REMINDER,
                    ['url' => $request->isLoan() ? route('loans.index') : route('connection.status')],
                ));
            } catch (\Throwable $exception) {
                Log::warning('Assigned schedule notification could not be recorded.', ['reschedule_request_id' => $request->id, 'error' => $exception->getMessage()]);
            }
        });

        return $sent;
    }

    public function sendAssignmentEmail(InstallationRescheduleRequest $request): bool
    {
        $consumer = $request->consumer;
        $recipient = $consumer->email ?: $consumer->user?->email;
        $result = ConsumerNotificationLog::sendOnce(
            consumerId: $consumer->id,
            event: 'installation_reschedule_'.$request->id.'_assigned',
            recipient: $recipient,
            send: function () use ($request, $consumer, $recipient): void {
                $isReconnection = $request->isReconnection();
                $view = $isReconnection ? 'emails.consumer.reconnection-reschedule-decision' : 'emails.consumer.installation-rescheduled';
                $emailUrl = app(ConsumerEmailUrl::class);
                $rescheduleUrl = $request->isLoan() && $request->assignment_number < self::LIMIT
                    ? $emailUrl->forLoanInstallationReschedule($request->loan_id)
                    : null;
                $rescheduleRemaining = null;
                if ($isReconnection) {
                    $reconnection = $request->reconnection ?? throw new RuntimeException('The reconnection schedule could not be found.');
                    $rescheduleRemaining = $this->usage($consumer, $reconnection)['remaining'];
                    if ($rescheduleRemaining > 0 && app(ReconnectionRescheduleService::class)->isCurrent($consumer, $reconnection)) {
                        $rescheduleUrl = $emailUrl->forReconnectionReschedule($reconnection->id);
                    }
                }
                $subject = $isReconnection ? 'Updated Reconnection Schedule' : 'New Installation Schedule Assigned';
                $sent = Mail::send($view, [
                    'consumer' => $consumer, 'reschedule' => $request, 'rescheduleRequest' => $request, 'registeredEmail' => $recipient,
                    'newSchedule' => $request->formattedNewSchedule(), 'originalSchedule' => $request->formattedOriginalSchedule(),
                    'adminNotes' => $request->admin_notes,
                    'rescheduleUrl' => $rescheduleUrl, 'rescheduleRemaining' => $rescheduleRemaining,
                    'loginUrl' => $isReconnection ? $emailUrl->tryTo(route('login', absolute: false)) : null,
                ], function ($message) use ($recipient, $subject): void {
                    $message->to($recipient)->subject(config('app.name').' - '.$subject);
                });

                if ($request->isLoan() && $sent === null) {
                    throw new RuntimeException('The mailer did not submit the updated loan installation schedule.');
                }
                if ($isReconnection && $sent === null) {
                    throw new RuntimeException('The mailer did not submit the updated reconnection schedule.');
                }
            },
        );

        return $result !== ConsumerNotificationLog::RESULT_FAILED;
    }
}
