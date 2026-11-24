<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  array<int, string>  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response|RedirectResponse
    {
        $user = $request->user();

        $allowedRoles = array_map(fn (string $role): string => $this->normalizeRole($role), $roles);
        $userRole = $user ? $this->normalizeRole($user->role) : null;

        if (! $user || ! in_array($userRole, $allowedRoles, true)) {
            abort(403);
        }

        return $next($request);
    }

    private function normalizeRole(string $role): string
    {
        return $role === 'meter_reader' ? 'staff' : $role;
    }
}
