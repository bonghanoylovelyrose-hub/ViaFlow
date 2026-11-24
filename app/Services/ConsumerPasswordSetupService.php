<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConsumerPasswordSetupService
{
    public const TEMPORARY_PASSWORD_LENGTH = 8;

    public const TEMPORARY_LOGIN_SESSION = 'consumer_temporary_password_login';

    public function createLink(Consumer $consumer, ?Request $resetRequest = null, ?string $temporaryPasswordHash = null): string
    {
        $consumer->load('user');
        abort_unless($consumer->user?->isConsumer() && $consumer->isVerified(), 403);
        $token = Str::random(64);
        $expires = now()->addMinutes((int) config('auth.passwords.users.expire', 60));
        $path = URL::temporarySignedRoute('password.setup', $expires, ['user' => $consumer->user_id, 'token' => $token], absolute: false);
        $emailUrl = app(ConsumerEmailUrl::class);
        $url = $resetRequest ? $emailUrl->forPasswordReset($path, $resetRequest) : $emailUrl->to($path);
        $recipient = trim($consumer->email ?: $consumer->user->email);
        $temporaryPasswordHash ??= $this->setupFor($consumer->user)?->temporary_password_hash;
        DB::table('consumer_password_setups')->updateOrInsert(['user_id' => $consumer->user_id], [
            'consumer_id' => $consumer->id,
            'token_hash' => hash('sha256', $token),
            'temporary_password_hash' => $temporaryPasswordHash,
            'recipient_email' => $recipient,
            'expires_at' => $expires,
            'created_at' => now(),
        ]);

        return $url;
    }

    public function verifyTemporaryPassword(User $user, string $password): ?string
    {
        if (strlen($password) !== self::TEMPORARY_PASSWORD_LENGTH) {
            return null;
        }

        return DB::transaction(function () use ($user, $password): ?string {
            $locked = User::query()->lockForUpdate()->find($user->id);

            return $locked ? $this->matchTemporaryPassword($locked, $password) : null;
        });
    }

    private function matchTemporaryPassword(User $user, string $password): ?string
    {
        if (strlen($password) !== self::TEMPORARY_PASSWORD_LENGTH) {
            return null;
        }

        $attemptsKey = 'consumer-temporary-password:'.$user->id;

        $hash = $this->setupFor($user, lock: true)?->temporary_password_hash;
        if (! $hash || RateLimiter::tooManyAttempts($attemptsKey, 10)) {
            return null;
        }
        if (! Hash::check($password, $hash)) {
            RateLimiter::hit($attemptsKey, 60);

            return null;
        }

        RateLimiter::clear($attemptsKey);

        return hash('sha256', $hash);
    }

    public function validate(User $user, string $token, bool $lock = false, bool $temporaryLogin = false): void
    {
        $record = $this->setupFor($user, $lock);
        // The signed email link has its own expiry. A temporary-password session
        // is bound to the current credential and survives link expiry or renewal.
        $valid = $temporaryLogin
            ? filled($record?->temporary_password_hash) && hash_equals(hash('sha256', $record->temporary_password_hash), $token)
            : $record && now()->lt($record->expires_at) && hash_equals($record->token_hash, hash('sha256', $token));
        abort_unless($valid,
            403, 'This password setup has expired, is invalid, or has already been used.');
    }

    private function setupFor(User $user, bool $lock = false): ?object
    {
        $query = DB::table('consumer_password_setups')->where('user_id', $user->id);
        $record = ($lock ? $query->lockForUpdate() : $query)->first();
        $consumer = $user->consumer()->first();
        if ($record && $user->isConsumer() && $consumer?->isVerified()
            && (int) $record->consumer_id === $consumer->id
            && mb_strtolower(trim($record->recipient_email)) === mb_strtolower(trim($consumer->email ?: $user->email))) {
            return $record;
        }

        return null;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        DB::transaction(function () use ($user, $currentPassword, $newPassword): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! Hash::check($currentPassword, $locked->password) && ! $this->matchTemporaryPassword($locked, $currentPassword)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }

            $temporaryHash = $this->setupFor($locked, lock: true)?->temporary_password_hash;
            if ($temporaryHash && Hash::check($newPassword, $temporaryHash)) {
                throw ValidationException::withMessages(['password' => 'Choose a new password that differs from the temporary password.']);
            }

            $this->saveNewPassword($locked, $newPassword);
        });
    }

    public function reset(User $user, string $token, string $password, bool $temporaryLogin = false): void
    {
        DB::transaction(function () use ($user, $token, $password, $temporaryLogin): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->validate($user, $token, lock: true, temporaryLogin: $temporaryLogin);
            if ($user->must_change_reactivation_password && Hash::check($password, $user->password)) {
                throw ValidationException::withMessages(['password' => 'Choose a new password that differs from the temporary password.']);
            }
            $this->saveNewPassword($user, $password);
        });
    }

    private function saveNewPassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => null,
            'remember_expires_at' => null,
            'must_change_password' => false,
            'must_change_reactivation_password' => false,
        ])->save();
        DB::table('consumer_password_setups')->where('user_id', $user->id)->delete();
    }
}
