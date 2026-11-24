<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class ConsumerEmailUrl
{
    public function forLoanInstallationReschedule(int $loanId): string
    {
        $path = URL::signedRoute('installation.reschedule.loan', ['loan' => $loanId], absolute: false);

        return $this->tryTo($path) ?? url($path);
    }

    public function forReconnectionReschedule(int $reconnectionId): string
    {
        $path = URL::temporarySignedRoute('reconnection.reschedule', now()->addDays(7), ['reconnection' => $reconnectionId], absolute: false);
        if ($publicUrl = $this->tryTo($path)) {
            return $publicUrl;
        }

        $localUrl = rtrim((string) config('app.url'), '/');
        $parts = parse_url($localUrl);
        if (app()->environment('local', 'testing') && in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && in_array(strtolower(trim($parts['host'] ?? '', '[]')), ['localhost', '127.0.0.1', '::1'], true)
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['query']) && ! isset($parts['fragment'])) {
            return $localUrl.'/'.ltrim($path, '/');
        }

        throw new RuntimeException('Set APP_PUBLIC_URL to the public application URL before sending reconnection reschedule links.');
    }

    public function to(string $path): string
    {
        return $this->tryTo($path)
            ?? throw new RuntimeException('Set APP_PUBLIC_URL to the public application URL before sending consumer account emails.');
    }

    public function forPasswordReset(string $path, Request $request): string
    {
        return $this->tryTo($path) ?? $this->loopbackUrl($path, $request)
            ?? throw new RuntimeException('Set APP_PUBLIC_URL to the public application URL before sending password reset links.');
    }

    public function forConnectionApplication(string $path, Request $request): string
    {
        return $this->tryTo($path) ?? $this->loopbackUrl($path, $request)
            ?? throw new RuntimeException('Set APP_PUBLIC_URL to the public application URL before sending consumer account emails.');
    }

    private function loopbackUrl(string $path, Request $request): ?string
    {
        $hosts = ['localhost', '127.0.0.1', '::1'];
        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! app()->isLocal() || ! in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            || ! in_array(trim($request->getHost(), '[]'), $hosts, true)
            || ! in_array(strtolower(trim((string) $configuredHost, '[]')), $hosts, true)) {
            return null;
        }

        return rtrim($request->root(), '/').'/'.ltrim($path, '/');
    }

    public function tryTo(string $path): ?string
    {
        $url = rtrim((string) config('app.public_url'), '/');
        $parts = parse_url($url);
        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        $isPublicHost = filter_var($host, FILTER_VALIDATE_IP)
            ? filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false
            : str_contains(rtrim($host, '.'), '.') && ! str_ends_with(rtrim($host, '.'), '.localhost')
                && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ! $isPublicHost
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        return $url.'/'.ltrim($path, '/');
    }
}
