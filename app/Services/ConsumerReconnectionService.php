<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\ConsumerReconnection;
use App\Models\InstallationRescheduleRequest;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\ReconnectionSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumerReconnectionService
{
    public function __construct(
        private readonly ActivationEmailService $emails,
    ) {}

    public function eligibility(Consumer $consumer): array
    {
        $notices = app(DisconnectionNoticeService::class);
        $outstanding = $notices->outstandingForReconnection($consumer);
        $loanIds = $notices->disconnectedLoanIds($consumer);
        $verified = Payment::query()->where('consumer_id', $consumer->id)->where('status', 'Verified')
            ->where(fn ($query) => $query->where('payment_type', 'Water')->orWhereIn('loan_id', $loanIds))
            ->whereNotNull('verified_at')->whereHas('verifier', fn ($query) => $query->where('role', 'admin'))
            ->when($consumer->disconnected_at !== null, fn ($query) => $query->where('verified_at', '>=', $consumer->disconnected_at))
            ->pluck('id')->all();

        $reason = match (true) {
            $consumer->reconnection_pending => 'This account already has a pending reconnection schedule.',
            ! in_array($consumer->status, Consumer::DISCONNECTION_ELIGIBLE_STATUSES, true) => 'Only inactive or disconnected accounts can be reconnected.',
            round($outstanding, 2) > 0 => 'Record and verify the full required payment in Payments. Remaining required balance: PHP '.number_format($outstanding, 2).'.',
            blank($consumer->account_number) || blank($consumer->meter_number) => 'The account and meter numbers must be assigned before reconnection.',
            default => null,
        };

        return ['eligible' => $reason === null, 'reason' => $reason, 'outstanding' => round($outstanding, 2), 'verified_payment_ids' => $verified];
    }

    public function reconnect(Consumer $consumer, User $admin, Carbon $scheduledAt): string
    {
        abort_unless($admin->isAdmin(), 403);
        $date = ReconnectionSchedule::format($scheduledAt, 'Y-m-d');
        $scheduledAt = $scheduledAt->copy()->utc();
        $record = DB::transaction(function () use ($consumer, $admin, $date, $scheduledAt): ConsumerReconnection {
            $account = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
            $eligibility = $this->eligibility($account);
            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages(['reconnection' => $eligibility['reason']]);
            }
            if ($scheduledAt->lessThan(now()->startOfSecond())) {
                throw ValidationException::withMessages(['installation_scheduled_at' => 'Choose a scheduled reconnection date and time on or after the current time in the Philippines.']);
            }
            if ($account->disconnected_at && $scheduledAt->lessThan($account->disconnected_at)) {
                throw ValidationException::withMessages(['installation_scheduled_at' => 'Enter a reconnection date and time on or after the disconnection.']);
            }
            $history = app(ConsumerDisconnectionHistoryService::class);
            $history->recordSettlement($account);
            $record = ConsumerReconnection::query()->create([
                'consumer_id' => $account->id,
                'disconnection_notice_id' => $history->currentNotice($account)?->id,
                'confirmed_by' => $admin->id,
                'disconnected_at' => $account->disconnected_at,
                'reconnection_date' => $date,
                'installation_scheduled_at' => $scheduledAt,
                'activated_at' => now(),
                'payment_settled_at' => $history->settlementDateFor($account),
                'reconnection_fee' => 0,
                'verified_payment_ids' => $eligibility['verified_payment_ids'],
            ]);
            $account->forceFill([
                'status' => 'Active',
                'reconnection_fee' => 0,
                'reconnection_pending' => true,
                'reconnected_at' => null,
                'reconnection_date' => $date,
                'reconnection_scheduled_at' => $scheduledAt,
            ])->save();
            $account->serviceRequests()->where('request_type', ServiceRequest::TYPE_RECONNECTION)
                ->where('status', ServiceRequest::STATUS_PENDING)
                ->update(['status' => ServiceRequest::STATUS_APPROVED, 'reviewed_at' => now(), 'reviewed_by_user_id' => $admin->id]);
            ConsumerNotificationLog::query()->create([
                'consumer_id' => $account->id,
                'event' => $record->scheduleEmailEvent(),
                'channel' => 'email',
                'recipient' => $account->email,
                'status' => ConsumerNotificationLog::STATUS_PENDING,
            ]);

            return $record;
        });
        $consumer->refresh();
        $result = ConsumerNotificationLog::RESULT_PENDING;
        DB::afterCommit(function () use ($consumer, $record, &$result): void {
            $result = $this->emails->send($consumer, $record);
        });

        return $result;
    }

    public function complete(Consumer $consumer, ConsumerReconnection $reconnection, User $admin): void
    {
        abort_unless($admin->isAdmin(), 403);

        DB::transaction(function () use ($consumer, $reconnection, $admin): void {
            $account = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
            $record = $account->reconnections()->lockForUpdate()->findOrFail($reconnection->id);
            if ($record->completed_at) {
                return;
            }

            if (! $account->reconnection_pending || $account->status !== 'Active'
                || ! $account->reconnection_scheduled_at || (int) $account->reconnections()->max('id') !== $record->id) {
                throw ValidationException::withMessages(['reconnection' => 'Only the current pending reconnection can be marked complete.']);
            }

            $completedAt = now();
            $record->update(['completed_at' => $completedAt, 'completed_by' => $admin->id]);
            $account->forceFill(['reconnection_pending' => false, 'reconnected_at' => $completedAt])->save();
            $account->serviceRequests()->where('request_type', ServiceRequest::TYPE_RECONNECTION)
                ->whereIn('status', [ServiceRequest::STATUS_PENDING, ServiceRequest::STATUS_APPROVED])
                ->update(['status' => ServiceRequest::STATUS_COMPLETED, 'reviewed_at' => $completedAt, 'reviewed_by_user_id' => $admin->id]);
            $account->installationRescheduleRequests()->where('consumer_reconnection_id', $record->id)->pending()
                ->update(['status' => InstallationRescheduleRequest::STATUS_CANCELLED, 'reviewed_at' => $completedAt, 'reviewed_by_user_id' => $admin->id]);
        });
    }
}
