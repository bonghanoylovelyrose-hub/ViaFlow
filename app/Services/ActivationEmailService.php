<?php

namespace App\Services;

use App\Mail\ReconnectionScheduleConfirmation;
use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\ConsumerReconnection;
use App\Models\User;
use App\Notifications\ConsumerNoticeNotification;
use App\Support\ReconnectionSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class ActivationEmailService
{
    public function __construct(
        private readonly ConsumerCredentialService $credentials,
        private readonly ConsumerPasswordSetupService $passwords,
        private readonly ConsumerEmailUrl $publicUrl,
        private readonly InstallationRescheduleService $reschedules,
    ) {}

    public function send(Consumer $consumer, ConsumerReconnection $reconnection): string
    {
        $result = DB::transaction(function () use ($consumer, $reconnection): string {
            $account = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
            $latest = $account->reconnections()->latest('id')->lockForUpdate()->first();
            if ($account->status !== 'Active' || $latest?->id !== $reconnection->id) {
                return ConsumerNotificationLog::RESULT_FAILED;
            }
            $recipient = trim((string) $account->email);
            if ($recipient === '') {
                $user = $account->user;
                if ($user?->isConsumer() && $user->email_verified_at) {
                    $recipient = trim((string) $user->email);
                }
            }
            $result = ConsumerNotificationLog::sendOnce(
                consumerId: $account->id,
                event: $latest->scheduleEmailEvent(),
                recipient: $recipient,
                send: function () use ($account, $latest, $recipient): void {
                    if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                        throw new RuntimeException('A valid registered consumer email address is required.');
                    }
                    if (DB::table('consumer_email_verifications')->where('consumer_id', $account->id)->whereNull('verified_at')->exists()) {
                        throw new RuntimeException('Verify the changed consumer email address before sending the reconnection schedule.');
                    }
                    if (! $this->usesDeliveryTransport((string) config('mail.default'))) {
                        throw new RuntimeException('Reconnection schedule email requires a mail transport that submits messages to a mail server.');
                    }

                    $loginUrl = $this->publicUrl->tryTo(route('login', absolute: false));
                    $rescheduleRemaining = $this->reschedules->usage($account, $latest)['remaining'];
                    $rescheduleUrl = $account->reconnection_pending && ! $latest->completed_at && $rescheduleRemaining > 0
                        ? $this->publicUrl->forReconnectionReschedule($latest->id)
                        : null;
                    $message = Mail::send((new ReconnectionScheduleConfirmation(
                        consumer: $account,
                        registeredEmail: $recipient,
                        scheduledAt: $latest->scheduledAt($account),
                        credentials: $loginUrl ? $this->credentialsFor($account, $latest, $recipient) : null,
                        loginUrl: $loginUrl,
                        rescheduleUrl: $rescheduleUrl,
                        rescheduleRemaining: $rescheduleRemaining,
                        adminNotes: $latest->latestAssignedReschedule?->admin_notes,
                    ))->to($recipient));

                    if ($message === null) {
                        throw new RuntimeException('The mailer did not submit the reconnection schedule confirmation.');
                    }
                },
            );
            if ($result === ConsumerNotificationLog::RESULT_SENT) {
                $latest->update(['pending_credentials' => null]);
            }

            return $result;
        });
        if ($result === ConsumerNotificationLog::RESULT_SENT) {
            try {
                $status = $consumer->reconnection_pending ? 'Active / Pending Reconnection' : 'Active / Connected';
                $schedule = ReconnectionSchedule::format($reconnection->scheduledAt($consumer));
                $consumer->user?->notify(new ConsumerNoticeNotification('Account Activated - Reconnection Schedule',
                    'Your account '.$consumer->account_number.' is '.$status.'. Scheduled reconnection: '.$schedule.' (Philippine time).'));
            } catch (Throwable $exception) {
                Log::warning('Activation account notification could not be recorded.', ['consumer_id' => $consumer->id, 'error' => $exception->getMessage()]);
            }
        }

        return $result;
    }

    private function usesDeliveryTransport(string $mailer): bool
    {
        $config = config('mail.mailers.'.$mailer, []);

        return match ($config['transport'] ?? null) {
            'array' => app()->runningUnitTests(),
            'log', null => false,
            'failover', 'roundrobin' => ! empty($config['mailers'])
                && collect($config['mailers'])->every(fn ($member) => $this->usesDeliveryTransport($member)),
            default => true,
        };
    }

    private function credentialsFor(Consumer $account, ConsumerReconnection $record, string $recipient): ?array
    {
        try {
            $user = $account->user()->lockForUpdate()->first();
            $pending = $record->pending_credentials;
            if (! $user?->isConsumer() || ($pending && strcasecmp($pending['recipient'], $recipient) !== 0)) {
                return null;
            }
            if ($record->credentials_issued_at && (
                ! $pending || ! $user->must_change_reactivation_password
                || ! Hash::check($pending['temporary_password'], $user->password)
            )) {
                return null;
            }

            return DB::transaction(fn () => $this->prepareCredentials($account, $record, $user, $recipient, $pending));
        } catch (Throwable $exception) {
            Log::warning('Optional login setup omitted from the reconnection schedule email.', [
                'reconnection_id' => $record->id,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function prepareCredentials(Consumer $consumer, ConsumerReconnection $reconnection, User $user, string $recipient, ?array $pending): array
    {
        if (! $pending) {
            $pending = ['temporary_password' => $this->credentials->generateTemporaryPassword(), 'recipient' => $recipient];
            $this->credentials->applyTemporaryPassword($user, $pending['temporary_password']);
            $user->forceFill(['must_change_reactivation_password' => true])->save();
        }
        $setupUrl = $pending['password_setup_url'] ?? null;
        $token = $setupUrl ? basename(parse_url($setupUrl, PHP_URL_PATH)) : null;
        if (! $token || ! DB::table('consumer_password_setups')->where('user_id', $user->id)
            ->where('expires_at', '>', now())->where('token_hash', hash('sha256', $token))->exists()) {
            $pending['password_setup_url'] = $this->passwords->createLink($consumer);
        }
        $reconnection->update([
            'pending_credentials' => $pending,
            'credentials_issued_at' => $reconnection->credentials_issued_at ?? now(),
        ]);

        return $pending;
    }
}
