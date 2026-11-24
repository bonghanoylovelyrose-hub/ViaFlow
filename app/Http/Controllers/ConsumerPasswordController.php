<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Services\ConsumerCredentialService;
use App\Services\ConsumerPasswordSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ConsumerPasswordController extends Controller
{
    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function requestPasswordReset(Request $request, ConsumerCredentialService $credentials): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'meter_number' => ['required', 'string', 'max:50'],
        ], [
            'email.required' => 'Enter the email address registered on your consumer account.',
            'meter_number.required' => 'Enter the meter number assigned to your account.',
        ]);

        $consumer = $this->matchingConsumer($validated['email'], $validated['meter_number']);
        $response = redirect()->route('password.forgot');
        if ($consumer && $credentials->sendPasswordReset($consumer, $request) === ConsumerNotificationLog::RESULT_SENT) {
            return $response->with('status', 'Password reset email sent successfully! Please check your registered email inbox.');
        }

        return $response->with('warning', 'We could not send a password reset email. Check your registered email and meter number, then try again.');
    }

    private function matchingConsumer(string $email, string $meterNumber): ?Consumer
    {
        return Consumer::query()->verified()
            ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($email))])
            ->whereRaw('LOWER(TRIM(meter_number)) = ?', [mb_strtolower(trim($meterNumber))])
            ->whereHas('user', fn ($query) => $query->where('role', 'consumer'))
            ->first();
    }

    public function showChangeForm(Request $request): View
    {
        abort_unless($request->user()?->isConsumer(), 403);

        return view('auth.change-password');
    }

    public function updatePassword(Request $request, ConsumerPasswordSetupService $passwords): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isConsumer(), 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ]);

        $passwords->changePassword($user, $validated['current_password'], $validated['password']);
        $user->refresh();

        $request->session()->forget(ConsumerPasswordSetupService::TEMPORARY_LOGIN_SESSION);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Your password has been changed. You can now continue using ViaFlow.');
    }
}
