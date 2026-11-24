<?php

namespace App\Http\Middleware;

use App\Services\DisconnectedAccountService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictDisconnectedConsumerAccess
{
    private const ALLOWED_ROUTES = [
        'account.inactive', 'account.access-status', 'logout',
    ];

    public function __construct(private readonly DisconnectedAccountService $accounts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->isConsumer()) {
            $user->setRelation('consumer', $user->consumer()->first());
        }
        $restricted = $user?->isConsumer() && $this->accounts->isRestricted($user->consumer);
        view()->share('disconnectedAccountAccess', $restricted);

        if ($restricted && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            if (! $request->isMethodSafe() || $request->expectsJson()) {
                abort(403, 'Your water service is inactive. Visit the office to settle the full required payment and arrange authorized reactivation.');
            }

            $response = redirect()->route('account.inactive');
        } else {
            $response = $next($request);
        }

        if ($user?->isConsumer()) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
