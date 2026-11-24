<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Services\ConsumerCredentialService;
use App\Services\ConsumerEmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class ConsumerEmailVerificationController extends Controller
{
    public function show(Consumer $consumer, string $token, ConsumerEmailVerificationService $verification): View
    {
        $record = $verification->validate($consumer, $token);
        return view('auth.verify-consumer-email', [
            'email' => $consumer->email,
            'formAction' => URL::temporarySignedRoute('consumer.email.verify.confirm', Carbon::parse($record->expires_at), ['consumer' => $consumer->id, 'token' => $token]),
        ]);
    }

    public function confirm(Request $request, Consumer $consumer, string $token, ConsumerEmailVerificationService $verification, ConsumerCredentialService $credentials): View
    {
        $sent = DB::transaction(function () use ($consumer, $token, $verification, $credentials): bool {
            $locked = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
            $verification->validate($locked, $token, true);
            DB::table('consumer_email_verifications')->where('consumer_id', $locked->id)->update(['verified_at' => now(), 'updated_at' => now()]);
            if ($locked->user?->isConsumer()) {
                $locked->user->forceFill(['email_verified_at' => now()])->save();
                if ($locked->isVerified()) {
                    $result = $credentials->issueAdminCredentials($locked, ConsumerNotificationLog::EVENT_CREDENTIALS_RESENT);
                    if ($result !== ConsumerNotificationLog::RESULT_SENT) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'The email could not be delivered. Please try again or contact the administrator.']);
                    }
                    return true;
                }
            }
            return false;
        });
        return view('auth.consumer-email-verified', ['sent' => $sent]);
    }
}
