<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ConsumerPasswordSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ConsumerPasswordSetupController extends Controller
{
    public function show(Request $request, User $user, string $token, ConsumerPasswordSetupService $passwords): View
    {
        $this->validateSignature($request);
        $passwords->validate($user, $token);

        return view('auth.setup-password', ['formAction' => $request->getRequestUri()]);
    }

    public function store(Request $request, User $user, string $token, ConsumerPasswordSetupService $passwords): RedirectResponse
    {
        $this->validateSignature($request);

        return $this->completeReset($request, $user, $token, $passwords);
    }

    public function showTemporary(Request $request, ConsumerPasswordSetupService $passwords): View
    {
        [$user, $fingerprint] = $this->temporaryLogin($request);
        abort_unless($request->user()?->is($user), 403);
        $passwords->validate($user, $fingerprint, temporaryLogin: true);

        return view('auth.setup-password', ['formAction' => $request->getRequestUri()]);
    }

    public function storeTemporary(Request $request, ConsumerPasswordSetupService $passwords): RedirectResponse
    {
        [$user, $fingerprint] = $this->temporaryLogin($request);
        abort_unless($request->user()?->is($user), 403);

        return $this->completeReset($request, $user, $fingerprint, $passwords, temporaryLogin: true);
    }

    private function completeReset(
        Request $request,
        User $user,
        string $token,
        ConsumerPasswordSetupService $passwords,
        bool $temporaryLogin = false,
    ): RedirectResponse {
        $passwords->validate($user, $token, temporaryLogin: $temporaryLogin);
        $validated = $request->validate(['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]]);
        $passwords->reset($user, $token, $validated['password'], temporaryLogin: $temporaryLogin);

        $request->session()->forget(ConsumerPasswordSetupService::TEMPORARY_LOGIN_SESSION);
        if ($temporaryLogin) {
            $request->session()->regenerate(true);
        }

        if ((int) Auth::id() === $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('status', 'Your password has been set securely. Sign in with your meter number and new password.');
    }

    private function temporaryLogin(Request $request): array
    {
        $proof = $request->session()->get(ConsumerPasswordSetupService::TEMPORARY_LOGIN_SESSION);
        abort_unless(isset($proof['credential_fingerprint']), 403, 'Sign in with your temporary password to continue.');

        return [User::query()->findOrFail($proof['user_id']), $proof['credential_fingerprint']];
    }

    private function validateSignature(Request $request): void
    {
        // Existing email links were signed with their full URL.
        abort_unless($request->hasValidSignature(absolute: false) || $request->hasValidSignature(), 403);
    }
}
