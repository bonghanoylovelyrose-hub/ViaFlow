<?php

namespace App\Http\Controllers;

use App\Models\ConsumerReconnection;
use App\Models\InstallationRescheduleRequest;
use App\Services\InstallationRescheduleService;
use App\Services\ReconnectionRescheduleService;
use App\Support\ReconnectionSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class ReconnectionRescheduleController extends Controller
{
    private function authorizeAccess(Request $request, ConsumerReconnection $reconnection): Carbon
    {
        $consumer = $reconnection->consumer;
        $user = $request->user();
        if ($user?->isConsumer()) {
            abort_unless($consumer->user_id === $user->id, 403);
        }
        if ($request->has('signature')) {
            abort_unless($request->hasValidSignature(absolute: false) || $request->hasValidSignature(), 403);
        } else {
            abort_unless($user && ($user->isAdmin() || $consumer->user_id === $user->id), 403);
        }
        return app(ReconnectionRescheduleService::class)->assertCurrent($consumer, $reconnection);
    }

    public function show(Request $request, ConsumerReconnection $reconnection): View
    {
        $scheduledAt = $this->authorizeAccess($request, $reconnection);
        $history = InstallationRescheduleRequest::query()->where('consumer_reconnection_id', $reconnection->id)->latest('id')->get();

        return view('consumer.reconnection.reschedule', [
            'consumer' => $reconnection->consumer,
            'scheduledAt' => $scheduledAt,
            'rescheduleUsage' => app(InstallationRescheduleService::class)->usage($reconnection->consumer, $reconnection),
            'pendingRequest' => $history->first(fn ($entry) => $entry->isPending()),
            'history' => $history,
            'formAction' => $request->getBaseUrl().URL::temporarySignedRoute('reconnection.reschedule.store', now()->addHour(), ['reconnection' => $reconnection->id], absolute: false),
        ]);
    }

    public function store(Request $request, ConsumerReconnection $reconnection, ReconnectionRescheduleService $reschedules): RedirectResponse
    {
        $this->authorizeAccess($request, $reconnection);
        $validated = $request->validate([
            'preferred_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today(ReconnectionSchedule::TIMEZONE)->toDateString()],
            'preferred_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $reschedules->submit($reconnection, $validated);

        $path = URL::temporarySignedRoute('reconnection.reschedule', now()->addHour(), ['reconnection' => $reconnection->id], absolute: false);

        return redirect()->away($request->getBaseUrl().$path)
            ->with('success', 'Your reschedule request has been submitted for admin approval. Your confirmed schedule has not changed.');
    }
}
