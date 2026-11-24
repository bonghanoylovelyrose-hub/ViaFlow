<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ConsumerEmailVerificationService
{
    public function prepare(Consumer $consumer): string
    {
        $token = Str::random(64);
        $expires = now()->addHours(24);
        DB::table('consumer_email_verifications')->updateOrInsert(
            ['consumer_id' => $consumer->id],
            ['email' => $consumer->email, 'token_hash' => hash('sha256', $token), 'expires_at' => $expires, 'verified_at' => null, 'created_at' => now(), 'updated_at' => now()],
        );
        return URL::temporarySignedRoute('consumer.email.verify', $expires, ['consumer' => $consumer->id, 'token' => $token]);
    }

    public function send(Consumer $consumer, string $url): string
    {
        return ConsumerNotificationLog::sendOnce(
            consumerId: $consumer->id,
            event: 'consumer_email_verification_requested',
            recipient: $consumer->email,
            send: function () use ($consumer, $url): void {
                Mail::send('emails.consumer.verify-email-change', ['consumer' => $consumer, 'verificationUrl' => $url], function ($message) use ($consumer): void {
                    $message->to($consumer->email)->subject(config('app.name', 'ViaFlow System').' Verify Your Email');
                });
            },
            force: true,
        );
    }

    public function validate(Consumer $consumer, string $token, bool $lock = false): object
    {
        $query = DB::table('consumer_email_verifications')->where('consumer_id', $consumer->id);
        $record = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($record && $record->verified_at === null && Carbon::parse($record->expires_at)->isFuture()
            && hash_equals($record->token_hash, hash('sha256', $token))
            && mb_strtolower($record->email) === mb_strtolower((string) $consumer->email), 403, 'This verification link is invalid or expired.');
        return $record;
    }
}
