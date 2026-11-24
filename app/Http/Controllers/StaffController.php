<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isAdmin() && strcasecmp(auth()->user()->email, 'admin@example.com') === 0, 403);
        return view($this->adminViewPath('staff.index'), [
            'staffMembers' => User::query()
                ->whereIn('role', ['admin', 'staff', 'meter_reader'])
                ->orderBy('role')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() && strcasecmp($request->user()->email, 'admin@example.com') === 0, 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:meter_reader'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('staff.index')
            ->with('success', 'Meter reader account created successfully.');
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() && strcasecmp($request->user()->email, 'admin@example.com') === 0, 403);
        abort_unless($staff->isMeterReader() && ! $request->user()->is($staff), 403);

        $staff->forceFill(['remember_token' => null])->save();
        $staff->delete();

        return redirect()->route('staff.index')->with('success', 'Meter reader account removed successfully.');
    }
}
