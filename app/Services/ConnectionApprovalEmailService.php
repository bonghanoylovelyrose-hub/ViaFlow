<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class ConnectionApprovalEmailService
{
    public function __construct(
        private readonly ConsumerEmailUrl $urls,
        private readonly ConsumerPasswordSetupService $passwords,
    ) {}

    public function send(Consumer $consumer, Request $request, bool $resend = false): string
    {
        return DB::transaction(function () use ($consumer, $request, $resend): string {
            $account = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
            $user = $account->user()->lockForUpdate()->first();
            $recipient = trim((string) $account->email);
            if (! $user?->isConsumer() || $account->status !== 'Active' || ! $account->isVerified()
                || $recipient === '' || DB::table('consumer_email_verifications')->where('consumer_id', $account->id)->whereNull('verified_at')->exists()) {
                return ConsumerNotificationLog::RESULT_FAILED;
            }

            $failedApproval = ConsumerNotificationLog::query()
                ->where('consumer_id', $account->id)
                ->where('event', ConsumerNotificationLog::EVENT_CONNECTION_APPROVED)
                ->where('status', ConsumerNotificationLog::STATUS_FAILED)
                ->exists();
            $sendLoginDetails = $resend && ! $failedApproval;

            return ConsumerNotificationLog::sendOnce(
                consumerId: $account->id,
                event: $sendLoginDetails ? ConsumerNotificationLog::EVENT_CREDENTIALS_RESENT : ConsumerNotificationLog::EVENT_CONNECTION_APPROVED,
                recipient: $recipient,
                send: function () use ($account, $user, $recipient, $request, $failedApproval): void {
                    DB::transaction(function () use ($account, $user, $recipient, $request, $failedApproval): void {
                        $loginUrl = $this->urls->forPasswordReset(route('login', absolute: false), $request);
                        $saved = $account->connection_approval_credentials ?? [];
                        $setup = DB::table('consumer_password_setups')->where('user_id', $user->id)
                            ->where('consumer_id', $account->id)->first();
                        if ($setup && strcasecmp($setup->recipient_email, $recipient) !== 0) {
                            $setup = null;
                        }
                        $passwordHash = ($saved['uses_recovery_password'] ?? false) ? $setup?->temporary_password_hash : $user->password;
                        if (strcasecmp($saved['recipient'] ?? '', $recipient) !== 0 || ! $user->must_change_password
                            || ! $passwordHash || ! isset($saved['password']) || ! Hash::check($saved['password'], $passwordHash)) {
                            unset($saved['password'], $saved['uses_recovery_password']);
                        }
                        $saved['recipient'] = $recipient;
                        $validLink = $setup && now()->lt($setup->expires_at) ? $setup : null;
                        if ($failedApproval && $user->must_change_password && ! $validLink
                            && blank($setup?->temporary_password_hash) && ! isset($saved['password'])) {
                            // Older approvals saved only the password hash. Give these consumers
                            // the same alternative credential used by Forgot Password.
                            $saved['password'] = Str::random(ConsumerPasswordSetupService::TEMPORARY_PASSWORD_LENGTH);
                            $saved['uses_recovery_password'] = true;
                            $saved['setup_url'] = $this->passwords->createLink($account, $request, Hash::make($saved['password']));
                        } else {
                            $saved['setup_url'] = $this->setupUrl($account, $request, $validLink, $saved['setup_url'] ?? null);
                        }
                        $reschedulePath = URL::signedRoute('installation.reschedule.connection', ['consumer' => $account->id], absolute: false);

                        $this->requireDeliveryMailer();

                        $account->forceFill(['connection_approval_credentials' => $saved])->save();
                        $message = Mail::send('emails.consumer.account-activated', [
                            'consumer' => $account,
                            'loginUrl' => $loginUrl,
                            'passwordSetupUrl' => $saved['setup_url'],
                            'installationDate' => $account->installation_date?->format('F d, Y'),
                            'rescheduleUrl' => $this->urls->forPasswordReset($reschedulePath, $request),
                        ], fn ($message) => $message->to($recipient)->subject(config('app.name', 'ViaFlow System').' Consumer Account Approved'));
                        if ($message === null) {
                            throw new RuntimeException('The mailer did not submit the approval message.');
                        }

                    });
                },
                force: $sendLoginDetails,
            );
        });
    }

    public function recordScheduledInstallation(Consumer $consumer): void
    {
        if (! $consumer->hasSubmittedConnectionDocuments()) {
            return;
        }

        ConsumerNotificationLog::query()->firstOrCreate([
            'consumer_id' => $consumer->id,
            'event' => ConsumerNotificationLog::EVENT_CONNECTION_INSTALLATION_CONFIRMED,
        ], [
            'channel' => 'email',
            'recipient' => $consumer->email,
            'status' => ConsumerNotificationLog::STATUS_PENDING,
        ]);
    }

    public function sendInstallationConfirmation(Consumer $consumer, Request $request): string
    {
        return DB::transaction(function () use ($consumer, $request): string {
            $account = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
            $event = ConsumerNotificationLog::EVENT_CONNECTION_INSTALLATION_CONFIRMED;
            if (! $account->installation_date || $account->installation_completed_at
                || ! $account->isVerified() || $account->status !== 'Active'
                || ! ConsumerNotificationLog::query()->where('consumer_id', $account->id)->where('event', $event)->exists()) {
                return ConsumerNotificationLog::RESULT_SUPPRESSED;
            }

            $recipient = trim((string) $account->email);
            $user = $account->user;
            $meterNumber = trim((string) $account->meter_number);
            if (! $user?->isConsumer() || $meterNumber === '' || trim((string) $account->account_number) === ''
                || trim((string) $account->application_reference) === ''
                || trim((string) $account->full_name) === '' || trim((string) $account->service_address) === ''
                || blank($account->installation_time) || trim((string) $user->username) !== $meterNumber) {
                return ConsumerNotificationLog::sendOnce(
                    $account->id,
                    $event,
                    $recipient,
                    fn () => throw new RuntimeException('Installation confirmation requires a complete schedule, verified consumer account, account number, application reference, service address, and matching assigned meter username.'),
                );
            }

            return ConsumerNotificationLog::sendOnce($account->id, $event, $recipient, function () use ($account, $user, $meterNumber, $recipient, $request): void {
                if ($recipient === '' || DB::table('consumer_email_verifications')->where('consumer_id', $account->id)->whereNull('verified_at')->exists()) {
                    throw new RuntimeException('A verified email address is required for the installation confirmation.');
                }
                $this->requireDeliveryMailer();
                $reschedulePath = URL::signedRoute('installation.reschedule.connection', ['consumer' => $account->id], absolute: false);
                $credentials = $account->connection_approval_credentials ?? [];
                $setup = DB::table('consumer_password_setups')->where('user_id', $user->id)
                    ->where('consumer_id', $account->id)->first();
                $savedSetupUrl = (string) ($credentials['setup_url'] ?? '');
                $savedToken = basename(parse_url($savedSetupUrl, PHP_URL_PATH) ?: '');
                $setupLinkIsValid = $setup
                    && strcasecmp((string) $setup->recipient_email, $recipient) === 0
                    && now()->lt($setup->expires_at)
                    && $savedToken !== ''
                    && hash_equals((string) $setup->token_hash, hash('sha256', $savedToken));
                if (! $setupLinkIsValid) {
                    $credentials['setup_url'] = $this->passwords->createLink($account, $request);
                    $account->forceFill(['connection_approval_credentials' => $credentials])->save();
                }
                $installationDateTime = $account->installation_date->copy()->setTimezone('Asia/Manila');
                if ($account->installation_time) {
                    [$hour, $minute, $second] = array_pad(array_map('intval', explode(':', (string) $account->installation_time)), 3, 0);
                    $installationDateTime->setTime($hour, $minute, $second);
                }
                $message = Mail::send('emails.consumer.installation-rescheduled', [
                    'consumer' => $account,
                    'newSchedule' => $installationDateTime->format('F d, Y \\a\\t g:i A').' (Philippine Time)',
                    'rescheduleUrl' => $this->urls->forPasswordReset($reschedulePath, $request),
                    'loginUrl' => $this->urls->forPasswordReset(route('login', absolute: false), $request),
                    'meterUsername' => $meterNumber,
                    'passwordSetupUrl' => $credentials['setup_url'],
                ], fn ($message) => $message->to($recipient)->subject(config('app.name', 'ViaFlow System').' - Water Connection Installation Confirmed'));
                throw_unless($message, new RuntimeException('The mailer did not submit the installation confirmation.'));
            });
        });
    }

    private function requireDeliveryMailer(): void
    {
        $mailer = config('mail.mailers.'.config('mail.default'), []);
        $nonDeliveryTransports = app()->runningUnitTests() ? ['log'] : ['log', 'array'];
        if (in_array($mailer['transport'] ?? null, $nonDeliveryTransports, true)
            || array_intersect($nonDeliveryTransports, $mailer['mailers'] ?? [])) {
            throw new RuntimeException('Connection approval and scheduling require a mailer that confirms delivery submission.');
        }
    }

    private function setupUrl(Consumer $consumer, Request $request, ?object $record, ?string $previousUrl): string
    {
        if ($record) {
            $token = $previousUrl ? basename(parse_url($previousUrl, PHP_URL_PATH) ?: '') : '';
            if ($token !== '' && hash_equals($record->token_hash, hash('sha256', $token))) {
                $path = parse_url($previousUrl, PHP_URL_PATH).'?'.parse_url($previousUrl, PHP_URL_QUERY);

                return $this->urls->forPasswordReset($path, $request);
            }

            return $this->urls->forPasswordReset(route('password.forgot', absolute: false), $request);
        }

        return $this->passwords->createLink($consumer, $request);
    }
}
