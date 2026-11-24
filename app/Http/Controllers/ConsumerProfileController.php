<?php

namespace App\Http\Controllers;

use App\Services\ConsumerPasswordSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ConsumerProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('consumer.profile.index', [
            'consumer' => $request->user()->consumer,
        ]);
    }

    public function updatePassword(Request $request, ConsumerPasswordSetupService $passwords): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'current_password' => ['required', 'string', ...($user->isConsumer() ? [] : ['current_password'])],
            'password' => ['required', 'confirmed', Password::min(8), ...($user->isConsumer() ? ['different:current_password'] : [])],
        ], [
            'current_password.required' => 'Enter your current password.',
            'current_password.current_password' => 'The current password is incorrect.',
            'password.required' => 'Enter your new password.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.min' => 'The new password must be at least 8 characters.',
        ]);

        if ($user->isConsumer()) {
            $passwords->changePassword($user, $validated['current_password'], $validated['password']);
            $user->refresh();
            $request->session()->forget(ConsumerPasswordSetupService::TEMPORARY_LOGIN_SESSION);
        } else {
            $user->forceFill(['password' => Hash::make($validated['password'])])->save();
        }

        $request->session()->regenerate();

        return redirect()
            ->route('consumer.profile.show')
            ->with('success', 'Your password has been updated.');
    }
}
