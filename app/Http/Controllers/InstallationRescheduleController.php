<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\InstallationRescheduleRequest;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\InstallationRescheduleRequested;
use App\Services\DisconnectedAccountService;
use App\Services\InstallationRescheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InstallationRescheduleController extends Controller
{
    private const RESCHEDULE_CONFIRMATION_MESSAGE = 'Your reschedule request has been submitted successfully! The administrator has been notified and will review your request. You will receive an email once a decision has been made.';

    /**
     * Show connection reschedule form.
     */
    public function showConnection(Request $request, Consumer $consumer): View
    {
        $this->authorizeConnectionAccess($request, $consumer);

        $pendingRequest = $consumer->installationRescheduleRequests()
            ->where('status', InstallationRescheduleRequest::STATUS_PENDING)
            ->where('reschedule_type', InstallationRescheduleRequest::TYPE_CONNECTION)
            ->latest('id')
            ->first();

        $history = $consumer->installationRescheduleRequests()
            ->where('reschedule_type', InstallationRescheduleRequest::TYPE_CONNECTION)
            ->latest('id')
            ->get();

        $currentSchedule = $consumer->installation_date
            ? $consumer->installation_date->format('F d, Y')
            : 'Not scheduled yet';
        if ($consumer->installation_date && $consumer->installation_time) {
            $currentSchedule .= ' at '.Carbon::parse($consumer->installation_time)->format('g:i A');
        }

        $formAction = URL::signedRoute('installation.reschedule.connection.store', ['consumer' => $consumer->id]);

        return view('consumer.installation.reschedule', [
            'consumer' => $consumer,
            'rescheduleUsage' => app(InstallationRescheduleService::class)->usage($consumer),
            'isLoan' => false,
            'currentSchedule' => $currentSchedule,
            'pendingRequest' => $pendingRequest,
            'history' => $history,
            'formAction' => $formAction,
            'openedFromEmail' => $request->has('signature'),
            'returnToEmail' => $request->boolean('return_to_email'),
        ]);
    }

    /**
     * Submit connection reschedule request (consumer reason only, no date selection).
     */
    public function storeConnection(Request $request, Consumer $consumer): RedirectResponse
    {
        $this->authorizeConnectionAccess($request, $consumer);

        app(InstallationRescheduleService::class)->assertAvailable($consumer);

        if ($consumer->installation_date === null) {
            return back()->withErrors(['reason' => 'There is no currently scheduled installation date to reschedule.']);
        }

        $hasPending = $consumer->installationRescheduleRequests()
            ->where('status', InstallationRescheduleRequest::STATUS_PENDING)
            ->where('reschedule_type', InstallationRescheduleRequest::TYPE_CONNECTION)
            ->exists();

        if ($hasPending) {
            return back()->withErrors(['reason' => 'A reschedule request is already pending administrator review.']);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($consumer, $validated): void {
                $consumer = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
                app(InstallationRescheduleService::class)->assertAvailable($consumer);
                if ($consumer->installationRescheduleRequests()->pending()->where('reschedule_type', InstallationRescheduleRequest::TYPE_CONNECTION)->exists()) {
                    throw ValidationException::withMessages(['reason' => 'A reschedule request is already pending scheduling.']);
                }
                $rescheduleRequest = InstallationRescheduleRequest::create([
                    'reschedule_type' => InstallationRescheduleRequest::TYPE_CONNECTION,
                    'consumer_id' => $consumer->id,
                    'loan_id' => null,
                    'status' => InstallationRescheduleRequest::STATUS_PENDING,
                    'reason' => trim($validated['reason']),
                    'original_installation_date' => $consumer->installation_date ? Carbon::parse($consumer->installation_date)->toDateString() : null,
                    'original_installation_time' => $consumer->installation_time,
                ]);

                $admins = User::query()->where('role', 'admin')->get();

                if ($admins->isEmpty()) {
                    throw new \RuntimeException('No administrator account is available to receive the reschedule request.');
                }

                Notification::send($admins, new InstallationRescheduleRequested($rescheduleRequest));
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $e) {
            Log::error('Installation reschedule request could not be submitted.', [
                'consumer_id' => $consumer->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->withErrors([
                'reason' => 'Your reschedule request could not be submitted because the administrator could not be notified. Please try again.',
            ]);
        }

        $redirectRoute = URL::signedRoute('installation.reschedule.connection', ['consumer' => $consumer->id]);

        return redirect($redirectRoute)
            ->with('success', self::RESCHEDULE_CONFIRMATION_MESSAGE)
            ->with('reschedule_confirmation', $this->rescheduleConfirmation($request, false, $consumer, null));
    }

    /**
     * Show loan reschedule form.
     */
    public function showLoan(Request $request, Loan $loan): View
    {
        $this->authorizeLoanAccess($request, $loan);
        $consumer = $loan->consumer;

        $pendingRequest = $loan->installationRescheduleRequests()
            ->where('status', InstallationRescheduleRequest::STATUS_PENDING)
            ->latest('id')
            ->first();

        $history = $loan->installationRescheduleRequests()
            ->latest('id')
            ->get();

        $schedule = $loan->installationSchedule();
        $formAction = $request->has('signature')
            ? url(URL::signedRoute('installation.reschedule.loan.store', ['loan' => $loan->id], absolute: false))
            : route('installation.reschedule.loan.store', $loan);

        return view('consumer.installation.reschedule', [
            'consumer' => $consumer,
            'isLoan' => true,
            'rescheduleUsage' => app(InstallationRescheduleService::class)->usage($consumer),
            'loan' => $loan,
            'currentSchedule' => $schedule ? $schedule->format('F j, Y \a\t g:i A').' (Philippine Time)' : 'Not scheduled yet',
            'loanRescheduleUnavailableReason' => $this->loanRescheduleUnavailableReason($loan, $schedule),
            'pendingRequest' => $pendingRequest,
            'history' => $history,
            'formAction' => $formAction,
            'openedFromEmail' => $request->has('signature'),
            'returnToEmail' => $request->boolean('return_to_email'),
        ]);
    }

    /**
     * Submit loan reschedule request (consumer reason only, no date selection).
     */
    public function storeLoan(Request $request, Loan $loan): RedirectResponse
    {
        $this->authorizeLoanAccess($request, $loan);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($loan, $validated): void {
                $consumer = Consumer::query()->lockForUpdate()->findOrFail($loan->consumer_id);
                $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
                $schedule = $loan->installationSchedule();
                if ($reason = $this->loanRescheduleUnavailableReason($loan, $schedule)) {
                    throw ValidationException::withMessages(['reason' => $reason]);
                }
                app(InstallationRescheduleService::class)->assertAvailable($consumer);
                if ($loan->installationRescheduleRequests()->pending()->exists()) {
                    throw ValidationException::withMessages(['reason' => 'A reschedule request is already pending scheduling.']);
                }
                $rescheduleRequest = InstallationRescheduleRequest::create([
                    'reschedule_type' => InstallationRescheduleRequest::TYPE_LOAN,
                    'consumer_id' => $loan->consumer_id,
                    'loan_id' => $loan->id,
                    'status' => InstallationRescheduleRequest::STATUS_PENDING,
                    'reason' => trim($validated['reason']),
                    'original_installation_date' => $schedule->toDateString(),
                    'original_installation_time' => $schedule->format('H:i:s'),
                ]);

                $admins = User::query()->where('role', 'admin')->get();

                if ($admins->isEmpty()) {
                    throw new \RuntimeException('No administrator account is available to receive the reschedule request.');
                }

                Notification::send($admins, new InstallationRescheduleRequested($rescheduleRequest));
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $e) {
            Log::error('Installation reschedule request could not be submitted.', [
                'consumer_id' => $loan->consumer_id,
                'loan_id' => $loan->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->withErrors([
                'reason' => 'Your reschedule request could not be submitted because the administrator could not be notified. Please try again.',
            ]);
        }

        $redirectRoute = $request->has('signature')
            ? url(URL::signedRoute('installation.reschedule.loan', ['loan' => $loan->id], absolute: false))
            : route('installation.reschedule.loan', $loan);

        return redirect($redirectRoute)
            ->with('success', self::RESCHEDULE_CONFIRMATION_MESSAGE)
            ->with('reschedule_confirmation', $this->rescheduleConfirmation($request, true, $loan->consumer, $loan));
    }

    public function assign(Request $request, InstallationRescheduleRequest $rescheduleRequest): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $timezone = match (true) {
            $rescheduleRequest->isLoan() => Loan::INSTALLATION_TIMEZONE,
            $rescheduleRequest->isReconnection() => \App\Support\ReconnectionSchedule::TIMEZONE,
            default => config('app.timezone'),
        };
        $minDate = today($timezone)->toDateString();
        $validated = $request->validate([
            'new_installation_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$minDate],
            'new_installation_time' => ['required', 'date_format:H:i'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $sent = app(InstallationRescheduleService::class)->assign($rescheduleRequest, $request->user(), $validated);

        if ($rescheduleRequest->isLoan() && ! $sent) {
            return back()->with('email_error', 'The new installation schedule was saved, but the confirmation email could not be sent.');
        }

        return back()->with('success', 'New installation date and time assigned. The consumer has been notified.')
            ->with($sent ? 'info' : 'email_error', $sent ? 'Updated schedule email sent.' : 'The schedule was assigned, but the email failed. The delivery failure was logged.');
    }

    public function approve(Request $request, InstallationRescheduleRequest $rescheduleRequest): RedirectResponse
    {
        return $this->assign($request, $rescheduleRequest);
    }

    public function reject(Request $request, InstallationRescheduleRequest $rescheduleRequest): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort(410, 'Approval and rejection actions have been replaced by Assign New Schedule.');
    }

    private function authorizeConnectionAccess(Request $request, Consumer $consumer): void
    {
        abort_if(! $request->user()?->isAdmin() && app(DisconnectedAccountService::class)->isRestricted($consumer), 403, 'Your service is disconnected. Please contact the office.');

        if ($this->hasValidAccessSignature($request)) {
            return;
        }

        $user = $request->user();
        if ($user && ($user->isAdmin() || $user->consumer?->id === $consumer->id || (int) $user->id === (int) $consumer->user_id)) {
            return;
        }

        abort(403, 'Unauthorized access to connection installation reschedule.');
    }

    private function authorizeLoanAccess(Request $request, Loan $loan): void
    {
        abort_if(! $request->user()?->isAdmin() && app(DisconnectedAccountService::class)->isRestricted($loan->consumer), 403, 'Your service is disconnected. Please contact the office.');

        if ($this->hasValidAccessSignature($request)) {
            return;
        }

        $user = $request->user();
        if ($user && ($user->isAdmin() || $user->consumer?->id === $loan->consumer_id || (int) $user->id === (int) $loan->consumer?->user_id)) {
            return;
        }

        abort(403, 'Unauthorized access to loan installation reschedule.');
    }

    private function hasValidAccessSignature(Request $request): bool
    {
        if ($request->hasValidSignature() || $request->hasValidSignature(false)) {
            return true;
        }

        $appUrl = config('app.url');
        if (filled($appUrl) && $request->has('signature')) {
            try {
                $parsed = parse_url($appUrl);
                $scheme = $parsed['scheme'] ?? $request->getScheme();
                $host = $parsed['host'] ?? $request->getHost();
                $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
                $root = rtrim("{$scheme}://{$host}{$port}", '/');
                $qs = (string) $request->server->get('QUERY_STRING');
                $fullUrl = $root.'/'.$request->path().($qs ? '?'.$qs : '');

                $simRequest = Request::create($fullUrl, $request->getMethod());
                if ($simRequest->hasValidSignature()) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }

        return false;
    }

    /**
     * Build the post-submission confirmation payload shown in the reschedule modal.
     *
     * @return array{message: string, origin: string, destination: string}
     */
    private function rescheduleConfirmation(Request $request, bool $isLoan, Consumer $consumer, ?Loan $loan): array
    {
        $origin = $request->input('origin');

        if (! in_array($origin, ['email', 'portal'], true)) {
            $origin = auth()->check() ? 'portal' : 'email';
        }

        // A signed request page is the email entry point, even if the browser
        // happens to have an active portal session.
        if (auth()->guest()) {
            $origin = 'email';
        }

        $showRoute = $isLoan ? 'installation.reschedule.loan' : 'installation.reschedule.connection';
        $routeParameters = $isLoan
            ? ['loan' => $loan->id]
            : ['consumer' => $consumer->id];

        $destination = $origin === 'email'
            ? url(URL::signedRoute($showRoute, $routeParameters + ['return_to_email' => 1], absolute: ! $isLoan))
            : ($isLoan ? route('loans.index') : route('connection.status'));

        return [
            'message' => self::RESCHEDULE_CONFIRMATION_MESSAGE,
            'origin' => $origin,
            'destination' => $destination,
        ];
    }

    private function loanRescheduleUnavailableReason(Loan $loan, ?Carbon $schedule): ?string
    {
        return match (true) {
            $loan->installation_status === 'Completed' => 'This installation has already been completed.',
            ! $loan->isApprovedForInstallation() => 'Only approved loan installations can be rescheduled.',
            $schedule === null => 'An installation date and time must be assigned before you can request a reschedule.',
            default => null,
        };
    }
}
