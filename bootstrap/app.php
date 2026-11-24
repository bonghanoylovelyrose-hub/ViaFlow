<?php

use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(
        at: '*',
        headers: Request::HEADER_X_FORWARDED_PROTO
    );

    Authenticate::redirectUsing(static fn () => route('login'));

        RedirectIfAuthenticated::redirectUsing(static function (Request $request): string {
            /** @var User|null $user */
            $user = $request->user();

            return match (true) {
                $user?->isConsumer() && app(\App\Services\DisconnectedAccountService::class)->isRestricted($user->consumer) => route('account.inactive'),
                $user?->isConsumer() => route('dashboard'),
                $user?->isMeterReader() => route('dashboard'),
                default => route('dashboard'),
            };
        });

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'consumer.password' => \App\Http\Middleware\EnsureConsumerPasswordChanged::class,
        ]);

        $middleware->web(append: [\App\Http\Middleware\RestrictDisconnectedConsumerAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 419 || $request->expectsJson() || ! $request->hasSession()) {
                return null;
            }

            if ($request->routeIs('password.setup.store', 'reconnection.reschedule.store')) {
                if (! $request->hasValidSignature(absolute: false) && ! $request->hasValidSignature()) {
                    return null;
                }
                $retryUrl = $request->getRequestUri();
            } elseif ($request->routeIs('password.setup.temporary.store')) {
                $retryUrl = $request->getBaseUrl().route('password.setup.temporary', absolute: false);
            } elseif ($request->routeIs('login.attempt')) {
                $retryUrl = $request->getBaseUrl().route('login', absolute: false);
            } elseif ($request->routeIs('password.forgot.send')) {
                $retryUrl = $request->getBaseUrl().route('password.forgot', absolute: false);
            } elseif ($request->routeIs('consumer.password.update')) {
                $retryUrl = $request->getBaseUrl().route('consumer.password.change', absolute: false);
            } else {
                return null;
            }

            $request->session()->regenerateToken();
            $request->session()->flash('warning', 'Your session was refreshed. Please enter your details again.');

            return new \Illuminate\Http\RedirectResponse($retryUrl, 303);
        });
    })->create();
