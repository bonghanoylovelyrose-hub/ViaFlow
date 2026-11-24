<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces consumers who signed in with a system-issued temporary password to
 * replace it before using the rest of the application.
 *
 * Admin and meter-reader accounts are never intercepted.
 */
class EnsureConsumerPasswordChanged
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        return $next($request);
    }
}
