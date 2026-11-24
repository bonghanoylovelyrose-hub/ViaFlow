<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Consumer-only credential helpers.
 *
 * Consumers sign in with the meter number assigned to their account, and any
 * password issued by the system is temporary until the consumer replaces it.
 */
class ConsumerCredentialService
{
    public function generateTemporaryPassword(): string
    {
        return Str::password(12, symbols: false);
    }

    /**
     * Keeps the consumer's login username aligned with the currently assigned
     * meter. Admin and meter-reader usernames are never touched.
     */
    public function syncUsername(Consumer $consumer): void
    {
        $user = $consumer->user()->first();

        if (! $user || ! $user->isConsumer()) {
            return;
        }

        $meterNumber = trim((string) $consumer->meter_number);
        $username = $meterNumber !== '' ? $meterNumber : null;

        if ($user->username === $username) {
            return;
        }

        $user->forceFill(['username' => $username])->save();
    }

    /**
     * Applies a system-issued password and marks it for an optional reminder.
     */
    public function applyTemporaryPassword(User $user, string $temporaryPassword): void
    {
        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'remember_token' => null,
            'remember_expires_at' => null,
        ])->save();
        DB::table('consumer_password_setups')->where('user_id', $user->id)->delete();
    }

    /** Rotate credentials and deliver them together; delivery failure rolls back the rotation. */
    public function issueAdminCredentials(Consumer $consumer, string $event): string
    {
        try {
            return DB::transaction(function () use ($consumer, $event): string {
                $locked = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
                $user = $locked->user()->lockForUpdate()->first();
                if (! $user?->isConsumer() || ! $locked->isVerified() || blank($locked->email)
                    || DB::table('consumer_email_verifications')->where('consumer_id', $locked->id)->whereNull('verified_at')->exists()) {
                    throw new \RuntimeException('Consumer credentials are unavailable until the email and account are verified.');
                }
                $temporaryPassword = $this->generateTemporaryPassword();
                $this->syncUsername($locked);
                $this->applyTemporaryPassword($user, $temporaryPassword);
                $result = ConsumerNotificationLog::sendOnce(
                    consumerId: $locked->id,
                    event: $event,
                    recipient: $locked->email,
                    send: function () use ($locked, $temporaryPassword): void {
                        Mail::send('emails.consumer.login-credentials', ['consumer' => $locked, 'temporaryPassword' => $temporaryPassword], function ($message) use ($locked): void {
                            $message->to($locked->email)->subject(config('app.name', 'ViaFlow System').' Login Details');
                        });
                    },
                    force: true,
                );
                if ($result !== ConsumerNotificationLog::RESULT_SENT) {
                    throw new \RuntimeException('Credential email delivery failed.');
                }

                return $result;
            });
        } catch (\Throwable $exception) {
            Log::warning('Consumer credential delivery failed; password rotation rolled back.', ['consumer_id' => $consumer->id, 'error' => $exception->getMessage()]);
            try {
                ConsumerNotificationLog::query()->updateOrCreate(
                    ['consumer_id' => $consumer->id, 'event' => $event],
                    ['channel' => 'email', 'recipient' => $consumer->email, 'status' => ConsumerNotificationLog::STATUS_FAILED, 'error_message' => 'Credential delivery failed.', 'sent_at' => null],
                );
            } catch (\Throwable $logException) {
                Log::warning('Consumer credential failure could not be recorded.', ['consumer_id' => $consumer->id, 'error' => $logException->getMessage()]);
            }

            return ConsumerNotificationLog::RESULT_FAILED;
        }
    }

    /**
     * Emails a single-use reset link and temporary password.
     */
    public function sendPasswordReset(Consumer $consumer, Request $request): string
    {
        $recipient = trim($consumer->email);

        return ConsumerNotificationLog::sendOnce(
            consumerId: $consumer->id,
            event: ConsumerNotificationLog::EVENT_PASSWORD_TEMPORARY,
            recipient: $recipient,
            send: function () use ($consumer, $recipient, $request): void {
                DB::transaction(function () use ($consumer, $recipient, $request): void {
                    $account = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
                    $user = $account->user()->lockForUpdate()->first();
                    if (! $user?->isConsumer() || ! $account->isVerified() || trim($account->email) !== $recipient
                        || $account->meter_number !== $consumer->meter_number
                        || DB::table('consumer_email_verifications')->where('consumer_id', $account->id)->whereNull('verified_at')->exists()) {
                        throw new \RuntimeException('Password recovery requires an eligible consumer account and a verified email address.');
                    }

                    $passwords = app(ConsumerPasswordSetupService::class);
                    $previousTemporaryHash = DB::table('consumer_password_setups')
                        ->where('user_id', $user->id)->value('temporary_password_hash');
                    do {
                        $temporaryPassword = Str::random(ConsumerPasswordSetupService::TEMPORARY_PASSWORD_LENGTH);
                    } while (Hash::check($temporaryPassword, $user->password)
                        || ($previousTemporaryHash && Hash::check($temporaryPassword, $previousTemporaryHash)));

                    $message = Mail::send('emails.consumer.password-setup', [
                        'consumer' => $account,
                        'registeredEmail' => $recipient,
                        'temporaryPassword' => $temporaryPassword,
                        'loginUrl' => app(ConsumerEmailUrl::class)->forPasswordReset(route('login', absolute: false), $request),
                        'passwordSetupUrl' => $passwords->createLink($account, $request, Hash::make($temporaryPassword)),
                    ], fn ($message) => $message->to($recipient)->subject(config('app.name', 'ViaFlow System').' Password Reset'));

                    if ($message === null) {
                        throw new \RuntimeException('The password reset message was not sent by the mailer.');
                    }
                });
            },
            force: true,
        );
    }
}
