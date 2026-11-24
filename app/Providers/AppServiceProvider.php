<?php

namespace App\Providers;

use App\Mime\FinfoMimeTypeGuesser;
use App\Mime\SafeMimeTypeDetector;
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Incident;
use App\Models\InstallationRescheduleRequest;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\ServiceRequest;
use Illuminate\Pagination\Paginator;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use Symfony\Component\Mime\MimeTypes;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        ServeCommand::$passthroughVariables = array_values(array_unique([
            ...ServeCommand::$passthroughVariables,
            'SystemRoot',
            'WINDIR',
            'ComSpec',
            'PATHEXT',
            'TEMP',
            'TMP',
            'USERPROFILE',
            'LOCALAPPDATA',
            'APPDATA',
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        MimeTypes::getDefault()->registerGuesser(new FinfoMimeTypeGuesser);

        Storage::extend('local', function ($app, $config) {
            $visibility = PortableVisibilityConverter::fromArray(
                $config['permissions'] ?? [],
                $config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE
            );

            $links = ($config['links'] ?? null) === 'skip'
                ? LocalAdapter::SKIP_LINKS
                : LocalAdapter::DISALLOW_LINKS;

            $adapter = new LocalAdapter(
                $config['root'], $visibility, $config['lock'] ?? LOCK_EX, $links,
                new SafeMimeTypeDetector,
            );

            return new \Illuminate\Filesystem\LocalFilesystemAdapter(
                new \League\Flysystem\Filesystem($adapter), $adapter, $config
            );
        });

        view()->composer(['layouts.sidebar', 'layouts.navbar'], function ($view) {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            // The sidebar and navbar are rendered in the same request. Reuse
            // their shared metadata without caching it across requests or users.
            $cacheKey = 'layout_meta_'.$user->getAuthIdentifier();
            $cachedMeta = request()->attributes->get($cacheKey);
            if ($cachedMeta !== null) {
                $view->with('layoutMeta', $cachedMeta);

                return;
            }

            $pendingReschedulesCount = Schema::hasTable('installation_reschedule_requests')
                ? InstallationRescheduleRequest::query()->pending()->count()
                : 0;

            $pendingAlerts = match (true) {
                $user->isConsumer() => Bill::query()
                    ->whereHas('consumer', fn ($query) => $query->where('user_id', $user->id))
                    ->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])
                    ->count()
                    + (Schema::hasTable('service_requests')
                        ? ServiceRequest::query()
                            ->whereHas('consumer', fn ($query) => $query->where('user_id', $user->id))
                            ->where('status', 'Pending')
                            ->count()
                        : 0)
                    + (Schema::hasTable('incidents')
                        ? Incident::query()
                            ->whereHas('consumer', fn ($query) => $query->where('user_id', $user->id))
                            ->whereIn('status', ['Pending', 'Ongoing'])
                            ->count()
                        : 0),
                $user->isMeterReader() => MeterReading::query()
                    ->where('reader_id', $user->id)
                    ->whereIn('status', ['Pending', 'Needs Revisit'])
                    ->count(),
                default => Consumer::query()
                    // Count each account once even when a pending application
                    // also has a pending membership claim.
                    ->where(function ($query) {
                        $query->whereIn('status', ['Pending Verification', Consumer::STATUS_PENDING_REQUIREMENTS])
                            ->orWhere(function ($query) {
                                $query->where('requested_membership_type', Consumer::MEMBERSHIP_REQUEST_MEMBER)
                                    ->where('membership_verification_status', Consumer::MEMBERSHIP_VERIFICATION_PENDING);
                            });
                    })
                    ->count()
                    + Payment::query()->where('status', 'Pending')->count()
                    + (Schema::hasTable('service_requests')
                        // New connection requests are the tracking record for
                        // the pending consumer applications already counted
                        // above, so only reconnections add to the alert count.
                        ? ServiceRequest::query()
                            ->where('status', 'Pending')
                            ->where('request_type', ServiceRequest::TYPE_RECONNECTION)
                            ->count()
                        : 0)
                    + (Schema::hasTable('incidents')
                        ? Incident::query()->whereIn('status', ['Pending', 'Ongoing'])->count()
                        : 0)
                    + $pendingReschedulesCount
                    + Bill::query()->where('notice_level', \App\Services\ConsumerBalanceService::DISCONNECTION_NOTICE)->count(),
            };

            $layoutMeta = [
                'current_date' => now()->format('F d, Y'),
                'pending_alerts' => $pendingAlerts,
                'pending_reschedules_count' => $pendingReschedulesCount,
                'unread_notifications' => Schema::hasTable('notifications')
                    ? $user->unreadNotifications()->count()
                    : 0,
                'recent_notifications' => Schema::hasTable('notifications')
                    ? $user->notifications()->latest()->limit(5)->get()
                    : collect(),
                'system_health' => $pendingAlerts >= 15 ? 'Needs Attention' : 'Stable',
                'system_health_class' => $pendingAlerts >= 15
                    ? 'bg-warning-subtle text-warning-emphasis'
                    : 'bg-success-subtle text-success',
            ];
            request()->attributes->set($cacheKey, $layoutMeta);
            $view->with('layoutMeta', $layoutMeta);
        });
    }
}
