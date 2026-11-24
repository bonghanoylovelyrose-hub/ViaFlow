<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\ConsumerReconnection;
use App\Models\DisconnectionNotice;
use App\Models\InstallationRescheduleRequest;
use App\Models\MeterAssignment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ConnectionStatusNotification;
use App\Rules\FileMimeType;
use App\Services\ActivationEmailService;
use App\Services\ConnectionApprovalEmailService;
use App\Services\ConnectionApplicationCorrectionService;
use App\Services\ConnectionApplicationService;
use App\Services\ConnectionRequestService;
use App\Services\ConsumerBalanceService;
use App\Services\ConsumerDisconnectionHistoryService;
use App\Services\ConsumerReconnectionService;
use App\Services\ConsumerCredentialService;
use App\Services\ConsumerEmailVerificationService;
use App\Services\DisconnectionNoticeService;
use App\Services\DisconnectionRecordsService;
use App\Services\MeterAssignmentService;
use App\Services\RagStatusService;
use App\Services\ReconnectionFeeService;
use App\Support\ReconnectionSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConsumerController extends Controller
{
    public function billingHistory(Consumer $consumer, ConsumerBalanceService $balances): \Illuminate\Http\Response
    {
        $bills = $consumer->bills()->where('bill_type', 'Water')
            ->with(['payments' => fn ($query) => $query->where('status', 'Verified')
                ->where('payment_type', 'Water')->orderBy('payment_date')])
            ->latest('billing_period')->latest('id')->limit(12)->get();
        $remaining = array_replace([], ...array_values($balances->remainingBillPortionsFor($consumer->id)));
        $outstandingWater = $balances->recordedOutstandingFor($consumer->id);

        return response()->view('admin.consumers._billing-history', compact('bills', 'remaining', 'balances', 'outstandingWater'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $accountCategory = trim((string) $request->string('account_category'));
        if ($accountCategory === 'disconnection_history') {
            $statuses = ConsumerDisconnectionHistoryService::FILTERS;
            $status = (string) $request->string('history_status');
            $status = array_key_exists($status, $statuses) ? $status : 'all';

            return view($this->adminViewPath('consumers.disconnection-history'), [
                'records' => app(ConsumerDisconnectionHistoryService::class)->paginate($search, $status),
                'filters' => ['account_category' => $accountCategory, 'search' => $search, 'history_status' => $status],
                'historyStatuses' => $statuses,
            ]);
        }
        $accountCategory = array_key_exists($accountCategory, Consumer::ACCOUNT_CATEGORY_STATUSES)
            ? $accountCategory
            : '';

        // Bringing the active list up to date before rendering means a newly
        // delinquent account appears without waiting for the daily sweep.
        if ($accountCategory === 'active') {
            $this->syncOpenBillStatuses();
        }

        $pendingType = trim((string) $request->string('pending_type'));
        if (! in_array($pendingType, ['all', 'applications', 'reschedules'], true)) {
            $pendingType = 'all';
        }

        if ($accountCategory === '' && in_array($pendingType, ['applications', 'reschedules'], true)) {
            $accountCategory = 'pending';
        }

        $requestedMembership = trim((string) $request->string('membership'));
        $membershipVerification = trim((string) $request->string('membership_verification'));

        // Keep older links working while presenting only the three simplified
        // membership options in the Consumer Management filter.
        $legacyMembershipVerification = match ($requestedMembership) {
            'pending_member_verification' => 'pending',
            'verified_members' => 'verified',
            'rejected_membership' => 'rejected',
            default => null,
        };

        $membership = match ($requestedMembership) {
            'member', 'member_applicants', 'pending_member_verification', 'verified_members', 'rejected_membership' => 'member',
            'non_member' => 'non_member',
            default => '',
        };

        $requestedInstallationStatus = trim((string) $request->string('installation_status'));
        $installationStatus = in_array($requestedInstallationStatus, ['pending', 'completed'], true)
            ? $requestedInstallationStatus
            : 'all';

        if ($membership !== 'member') {
            $membershipVerification = '';
        } elseif ($legacyMembershipVerification !== null) {
            $membershipVerification = $legacyMembershipVerification;
        } elseif (! in_array($membershipVerification, ['pending', 'verified', 'rejected'], true)) {
            $membershipVerification = '';
        }

        $consumerQuery = Consumer::query()->when($accountCategory === 'active', fn ($query) => $query->with(['installationCompletedBy', 'latestReconnection']))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($consumerQuery) use ($search) {
                    $consumerQuery
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('application_reference', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('meter_number', 'like', "%{$search}%");
                });
            })
            ->when($accountCategory !== '', function ($query) use ($accountCategory, $pendingType) {
                if ($accountCategory === 'pending') {
                    if ($pendingType === 'applications') {
                        $query->whereIn('status', Consumer::ACCOUNT_CATEGORY_STATUSES['pending']);
                    } elseif ($pendingType === 'reschedules') {
                        $query->whereHas('installationRescheduleRequests', fn ($rq) => $rq->where('status', InstallationRescheduleRequest::STATUS_PENDING)->where('reschedule_type', '!=', InstallationRescheduleRequest::TYPE_LOAN));
                    } else {
                        $query->where(function ($q) {
                            $q->whereIn('status', Consumer::ACCOUNT_CATEGORY_STATUSES['pending'])
                                ->orWhereHas('installationRescheduleRequests', fn ($rq) => $rq->where('status', InstallationRescheduleRequest::STATUS_PENDING)->where('reschedule_type', '!=', InstallationRescheduleRequest::TYPE_LOAN));
                        });
                    }
                } elseif ($accountCategory === 'active') {
                    $query->activeAccounts()->whereDoesntHave('disconnectionNotices', fn ($notice) => $notice->active());
                } elseif ($accountCategory === 'inactive') {
                    $query->inactiveAccounts();
                } else {
                    $query->whereIn('status', Consumer::ACCOUNT_CATEGORY_STATUSES[$accountCategory]);
                }
            })
            ->when($accountCategory === 'active' && $installationStatus === 'pending', fn ($query) => $query->where(fn ($pending) => $pending
                ->where('reconnection_pending', true)
                ->orWhere(fn ($installation) => $installation->whereNull('installation_completed_at')->whereNotNull('installation_date')
                    ->whereDoesntHave('latestReconnection', fn ($reconnection) => $reconnection->whereNotNull('completed_at')))))
            ->when($accountCategory === 'active' && $installationStatus === 'completed', fn ($query) => $query
                ->where('reconnection_pending', false)
                ->where(fn ($completed) => $completed->whereNotNull('installation_completed_at')
                    ->orWhereHas('latestReconnection', fn ($reconnection) => $reconnection->whereNotNull('completed_at'))))
            ->when($membership === 'member', fn ($query) => $query->where('membership_type', Consumer::MEMBERSHIP_REQUEST_MEMBER))
            ->when($membership === 'non_member', fn ($query) => $query->where('membership_type', Consumer::MEMBERSHIP_REQUEST_NON_MEMBER))
            ->when($membershipVerification === 'pending', fn ($query) => $query
                ->where('requested_membership_type', Consumer::MEMBERSHIP_REQUEST_MEMBER)
                ->where('membership_verification_status', Consumer::MEMBERSHIP_VERIFICATION_PENDING))
            ->when($membershipVerification === 'verified', fn ($query) => $query
                ->where('membership_type', Consumer::MEMBERSHIP_REQUEST_MEMBER)
                ->whereIn('membership_verification_status', [
                    Consumer::MEMBERSHIP_VERIFICATION_VERIFIED,
                    Consumer::MEMBERSHIP_VERIFICATION_NOT_REQUIRED,
                ]))
            ->when($membershipVerification === 'rejected', fn ($query) => $query
                ->where('requested_membership_type', Consumer::MEMBERSHIP_REQUEST_MEMBER)
                ->where('membership_verification_status', Consumer::MEMBERSHIP_VERIFICATION_REJECTED))
            ;

        if ($accountCategory === 'inactive') {
            $perPage = 10;
            $page = LengthAwarePaginator::resolveCurrentPage();
            $balanceService = app(ConsumerBalanceService::class);
            $latestVerifiedPayments = DB::table('payments')
                ->selectRaw('consumer_id, MAX(verified_at) as latest_verified_at')
                ->where('is_billing_fixture', false)
                ->where('status', 'Verified')
                ->whereNotNull('verified_at')
                ->groupBy('consumer_id');

            $allMatchingConsumers = $consumerQuery
                ->leftJoinSub($latestVerifiedPayments, 'verified_payment_updates', 'verified_payment_updates.consumer_id', '=', 'consumers.id')
                ->select('consumers.*')
                ->addSelect('verified_payment_updates.latest_verified_at')
                ->get()
                ->map(function (Consumer $consumer) use ($balanceService) {
                    $consumer->setAttribute('_outstanding_balance', $balanceService->totalOutstandingFor($consumer->id));
                    $consumer->setAttribute('_list_updated_at', collect([
                        $consumer->full_payment_verified_at,
                        $consumer->disconnected_at,
                        $consumer->latest_verified_at,
                        $consumer->updated_at,
                    ])->filter()->map(fn ($date) => \Illuminate\Support\Carbon::parse($date)->timestamp)->max() ?? 0);

                    return $consumer;
                })
                ->sort(function (Consumer $left, Consumer $right) {
                    $leftPriority = $left->_outstanding_balance <= 0.0 ? 1 : 0;
                    $rightPriority = $right->_outstanding_balance <= 0.0 ? 1 : 0;

                    return ($rightPriority <=> $leftPriority)
                        ?: ($right->_list_updated_at <=> $left->_list_updated_at)
                        ?: ($right->id <=> $left->id);
                })
                ->values();

            $consumers = new LengthAwarePaginator(
                $allMatchingConsumers->forPage($page, $perPage)->values(),
                $allMatchingConsumers->count(),
                $perPage,
                $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()],
            );
        } else {
            if ($accountCategory === 'active') {
                $reconnectionActivity = ConsumerReconnection::query()
                    ->select('consumer_id')
                    ->selectRaw('MAX(CASE WHEN completed_at > activated_at THEN completed_at ELSE activated_at END) as occurred_at')
                    ->groupBy('consumer_id');

                // verified_at records the original activation. Compare it with
                // both reconnection history and the legacy account timestamp.
                $consumerQuery->leftJoinSub($reconnectionActivity, 'reconnection_activity', 'reconnection_activity.consumer_id', '=', 'consumers.id')
                    ->select('consumers.*')
                    ->orderByRaw('CASE
                        WHEN reconnection_activity.occurred_at IS NOT NULL
                            AND (consumers.reconnected_at IS NULL OR reconnection_activity.occurred_at >= consumers.reconnected_at)
                            AND (consumers.verified_at IS NULL OR reconnection_activity.occurred_at >= consumers.verified_at)
                            THEN reconnection_activity.occurred_at
                        WHEN consumers.reconnected_at IS NOT NULL
                            AND (consumers.verified_at IS NULL OR consumers.reconnected_at >= consumers.verified_at)
                            THEN consumers.reconnected_at
                        ELSE consumers.verified_at
                    END DESC')
                    ->orderByDesc('consumers.id');
            } else {
                $consumerQuery->orderByDesc('created_at');
            }

            $consumers = $consumerQuery->paginate(10)->withQueryString();
        }

        $rag = app(RagStatusService::class);
        $consumerRag = [];
        foreach ($consumers as $consumer) {
            $consumerRag[$consumer->id] = $rag->consumerStanding($consumer);
        }

        // Every meter ever issued, newest first, so replaced meters stay auditable.
        $meterHistory = MeterAssignment::query()
            ->whereIn('consumer_id', $consumers->pluck('id'))
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get(['consumer_id', 'meter_number', 'assigned_at', 'retired_at'])
            ->groupBy('consumer_id')
            ->map(fn ($rows) => $rows->map(fn (MeterAssignment $assignment) => [
                'meter_number' => $assignment->meter_number,
                'assigned_at' => optional($assignment->assigned_at)->toDateString(),
                'retired_at' => optional($assignment->retired_at)->toDateString(),
            ])->values())
            ->all();

        // The review modal shows the linked water connection request so the
        // connection decision and the membership decision never look like the
        // same status.
        $newConnectionRequests = ServiceRequest::query()
            ->whereIn('consumer_id', $consumers->pluck('id'))
            ->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)
            ->orderByDesc('id')
            ->get()
            ->groupBy('consumer_id')
            ->map(fn ($requests) => $requests->first());

        $disconnectionNotices = collect();
        $disconnectionNoticeCount = 0;

        $noticeSearch = trim((string) $request->string('notice_search'));

        $requestedNoticeMembership = trim((string) $request->string('notice_membership'));
        $noticeMembership = in_array($requestedNoticeMembership, ['member', 'non_member'], true) ? $requestedNoticeMembership : '';

        $noticeStatus = trim((string) $request->string('notice_status'));
        if (! array_key_exists($noticeStatus, DisconnectionRecordsService::FILTERS)) {
            $noticeStatus = '';
        }

        if ($accountCategory === 'active') {
            $disconnectionNotices = app(DisconnectionRecordsService::class)->paginate($noticeSearch, $noticeStatus, $noticeMembership);
            $disconnectionNoticeCount = $disconnectionNotices->total();
        }

        // The review modal and the table show exactly which requirements the
        // administrator asked the applicant to correct.
        $corrections = app(ConnectionApplicationCorrectionService::class);
        $correctionFieldsByConsumer = [];
        foreach ($consumers as $consumer) {
            $correctionFieldsByConsumer[$consumer->id] = $corrections->labelsFor(
                $corrections->normalize($consumer->rejection_fields, $consumer->rejection_reason),
            );
        }

        $pendingApplicationsCount = Consumer::query()->whereIn('status', Consumer::ACCOUNT_CATEGORY_STATUSES['pending'])->count();
        $pendingReschedulesCount = InstallationRescheduleRequest::query()->pending()->where('reschedule_type', '!=', InstallationRescheduleRequest::TYPE_LOAN)->count();

        $pendingReschedulesByConsumer = InstallationRescheduleRequest::query()
            ->with('reconnection')
            ->where('reschedule_type', '!=', InstallationRescheduleRequest::TYPE_LOAN)
            ->whereIn('consumer_id', $consumers->pluck('id'))
            ->where('status', InstallationRescheduleRequest::STATUS_PENDING)
            ->with(['reviewer', 'loan'])
            ->get()
            ->groupBy('consumer_id');

        $rescheduleHistoryByConsumer = InstallationRescheduleRequest::query()
            ->whereIn('consumer_id', $consumers->pluck('id'))
            ->with(['reviewer', 'loan'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('consumer_id');

        $reconnectionEligibility = [];
        if ($accountCategory === 'inactive') {
            foreach ($consumers as $consumer) {
                $reconnectionEligibility[$consumer->id] = app(ConsumerReconnectionService::class)->eligibility($consumer);
            }
        }

        $latestReconnectionIds = ConsumerReconnection::query()
            ->whereIn('consumer_id', $consumers->pluck('id'))->groupBy('consumer_id')
            ->selectRaw('consumer_id, MAX(id) as id')->pluck('id', 'consumer_id');
        $reconnectionEmails = ConsumerNotificationLog::query()
            ->whereIn('event', $latestReconnectionIds->map(fn ($id) => 'consumer_reconnected_'.$id)->values())
            ->orderBy('id')->get(['consumer_id', 'event', 'status', 'sent_at'])
            ->groupBy('event')->map(fn ($attempts) => $attempts->firstWhere('status', ConsumerNotificationLog::STATUS_SENT) ?? $attempts->last());

        return view($this->adminViewPath('consumers.index'), [
            'latestReconnectionIds' => $latestReconnectionIds,
            'reconnectionEmails' => $reconnectionEmails,
            'pendingEmailVerificationIds' => Schema::hasTable('consumer_email_verifications') ? array_map('intval', DB::table('consumer_email_verifications')->whereIn('consumer_id', $consumers->pluck('id'))->whereNull('verified_at')->pluck('consumer_id')->all()) : [],
            'reconnectionEligibility' => $reconnectionEligibility,
            'consumers' => $consumers,
            'consumerRag' => $consumerRag,
            'meterHistory' => $meterHistory,
            'newConnectionRequests' => $newConnectionRequests,
            'disconnectionNotices' => $disconnectionNotices,
            'disconnectionNoticeCount' => $disconnectionNoticeCount,
            'correctionFieldsByConsumer' => $correctionFieldsByConsumer,
            'correctionFieldOptions' => $corrections->options(),
            'pendingType' => $pendingType,
            'pendingApplicationsCount' => $pendingApplicationsCount,
            'pendingReschedulesCount' => $pendingReschedulesCount,
            'pendingReschedulesByConsumer' => $pendingReschedulesByConsumer,
            'rescheduleHistoryByConsumer' => $rescheduleHistoryByConsumer,
            'filters' => [
                'search' => $search,
                'account_category' => $accountCategory,
                'pending_type' => $pendingType,
                'membership' => $membership,
                'installation_status' => $installationStatus,
                'membership_verification' => $membershipVerification,
                'notice_search' => $noticeSearch,
                'notice_membership' => $noticeMembership,
                'notice_status' => $noticeStatus,
            ],
            'noticeStatusFilters' => DisconnectionRecordsService::FILTERS,
            'bulkUploadColumns' => $this->bulkUploadColumns(),
            'statuses' => ['Pending Verification', 'Active', 'For Review', 'Disconnected', 'For Reconnection', 'Rejected'],
            'membershipTypes' => ['Member', 'Non-Member'],
            'membershipFilters' => [
                '' => 'All Membership Records',
                'member' => 'Member',
                'non_member' => 'Non-Member',
            ],
        ]);
    }

    public function confirmDisconnection(
        Request $request,
        Consumer $consumer,
        DisconnectionNoticeService $notices,
    ): RedirectResponse {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate(['disconnection_reason' => ['nullable', 'string', 'max:1000']]);

        try {
            $notices->confirmDisconnection($consumer, $request->user(), $validated['disconnection_reason'] ?? null);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('consumers.index', ['account_category' => 'active'])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('consumers.index', ['account_category' => 'inactive'])
            ->with('success', 'Physical disconnection confirmed. The account has been moved to Inactive Accounts and the notice history was retained.');
    }

    public function updateReconnectionFee(Request $request, Consumer $consumer): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $validated = $request->validate(['reconnection_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2']]);
        app(ReconnectionFeeService::class)->assess($consumer, isset($validated['reconnection_fee']) ? (float) $validated['reconnection_fee'] : null);

        return redirect()->route('consumers.index', ['account_category' => 'inactive'])
            ->with('success', 'Outstanding reconnection fees removed.');
    }

    public function reconnect(Request $request, Consumer $consumer, ConsumerReconnectionService $reconnections): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        try {
            $validated = $request->validate([
                'confirm_full_payment' => ['accepted'],
                'installation_scheduled_at' => ['bail', 'required', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s,Y-m-d H:i,Y-m-d H:i:s'],
            ], [], ['installation_scheduled_at' => 'scheduled reconnection date and time']);
            $delivery = $reconnections->reconnect($consumer, $request->user(), ReconnectionSchedule::parse($validated['installation_scheduled_at']));
        } catch (ValidationException $exception) {
            $request->session()->flash('consumer_modal', 'reconnect');

            throw $exception;
        }

        return $this->withReconnectionEmailStatus(
            redirect()->route('consumers.index', ['account_category' => 'active'])
                ->with('success', 'Reconnection scheduled. The account is Active with Pending Reconnection.'),
            $delivery,
        );
    }

    public function completeReconnection(Request $request, Consumer $consumer, ConsumerReconnection $reconnection, ConsumerReconnectionService $reconnections): RedirectResponse
    {
        $reconnections->complete($consumer, $reconnection, $request->user());

        return redirect()->route('consumers.index', array_merge($request->query(), ['account_category' => 'active', 'page' => 1]))
            ->with('success', 'Reconnection marked as completed. Water service is Connected.');
    }

    public function retryReactivationEmail(Request $request, Consumer $consumer, ActivationEmailService $emails): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $reconnection = $consumer->reconnections()->latest('id')->firstOrFail();
        return $this->withReconnectionEmailStatus(
            redirect()->route('consumers.index', ['account_category' => 'active']),
            $emails->send($consumer, $reconnection),
        );
    }

    private function withReconnectionEmailStatus(RedirectResponse $response, string $delivery): RedirectResponse
    {
        return match ($delivery) {
            ConsumerNotificationLog::RESULT_SENT => $response->with('info', 'The mail server accepted the reconnection schedule email.'),
            ConsumerNotificationLog::RESULT_SUPPRESSED => $response->with('info', 'The mail server already accepted the reconnection schedule email.'),
            ConsumerNotificationLog::RESULT_PENDING => $response->with('info', 'The reconnection schedule email is pending submission.'),
            default => $response->with('email_error', 'The reconnection schedule email was not accepted. Use Resend Reconnection Schedule Email in View Details.'),
        };
    }

    public function store(Request $request, ConnectionApplicationService $applications): RedirectResponse
    {
        $member = $request->input('is_viohoa_member') === 'yes';
        $rules = ['is_viohoa_member' => ['required', 'in:yes,no']]
            + $applications->applicantRules($member, allowMissingDocuments: true)
            + ($member ? $applications->memberRules(allowMissingDocuments: true) : []);
        $validator = Validator::make($request->all(), $rules, AuthController::contactNumberMessages());
        if ($validator->fails()) {
            return redirect()->back(fallback: route('consumers.index'))
                ->withErrors($validator)->withInput()->with('consumer_modal', 'single');
        }

        $consumer = $applications->submit($validator->validated(), $member, allowMissingDocuments: true);
        $response = redirect()->route('consumers.index', ['account_category' => 'pending', 'pending_type' => 'applications'])
            ->with('success', 'Connection application '.$consumer->application_reference.' saved as '.$consumer->status.'.');

        if ($consumer->status === Consumer::STATUS_PENDING_REQUIREMENTS
            && $applications->sendRequirementsLink($consumer) === ConsumerNotificationLog::RESULT_FAILED) {
            $response->with('email_error', 'The application was saved, but the requirements email could not be sent. Check the application log for the cause.');
        }

        return $response;
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $request->validate([
            'consumer_csv' => ['required', 'file', 'extensions:csv,txt', new FileMimeType('csv,txt')],
        ]);

        $file = $request->file('consumer_csv');

        if (! $file || ! $file->isValid()) {
            return redirect()
                ->route('consumers.index')
                ->withErrors(['consumer_csv' => 'Upload a valid CSV file.'])
                ->with('consumer_modal', 'bulk');
        }

        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return redirect()
                ->route('consumers.index')
                ->withErrors(['consumer_csv' => 'The CSV file could not be opened.'])
                ->with('consumer_modal', 'bulk');
        }

        $expectedHeaders = $this->bulkUploadColumns();
        $headerRow = fgetcsv($handle) ?: [];
        $normalizedHeaderRow = array_map(fn ($value) => trim((string) $value), $headerRow);

        if ($normalizedHeaderRow !== $expectedHeaders) {
            fclose($handle);

            return redirect()
                ->route('consumers.index')
                ->withErrors([
                    'consumer_csv' => 'The CSV columns do not match the required format: '.implode(', ', $expectedHeaders),
                ])
                ->with('consumer_modal', 'bulk');
        }

        $rows = [];
        $seenAccountNumbers = [];
        $seenMeterNumbers = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $row = array_pad($row, count($expectedHeaders), '');
            $payload = array_combine($expectedHeaders, array_slice($row, 0, count($expectedHeaders)));

            if (! is_array($payload)) {
                fclose($handle);

                return redirect()
                    ->route('consumers.index')
                    ->withErrors(['consumer_csv' => "Row {$rowNumber} could not be read from the CSV file."])
                    ->with('consumer_modal', 'bulk');
            }

            $mapped = [
                'full_name' => trim((string) ($payload['Full Name'] ?? '')),
                'account_number' => trim((string) ($payload['Account Number'] ?? '')),
                'meter_number' => trim((string) ($payload['Meter Number'] ?? '')),
                'membership_type' => trim((string) ($payload['Membership Type'] ?? '')),
                'status' => trim((string) ($payload['Status'] ?? '')),
                'service_address' => trim((string) ($payload['Service Address'] ?? '')),
                'contact_number' => trim((string) ($payload['Contact Number'] ?? '')),
                'address_block' => null,
                'address_street' => null,
                'housing_rights_documented' => false,
                'association_member' => false,
                'association_registered' => false,
                'membership_renewal_date' => null,
            ];

            $validator = Validator::make($mapped, [
                'full_name' => ['required', 'string', 'max:255'],
                'account_number' => ['required', 'string', 'max:50', Rule::unique('consumers', 'account_number')],
                'meter_number' => ['required', 'string', 'max:50', Rule::unique('consumers', 'meter_number')],
                'membership_type' => ['required', 'in:Member,Non-Member'],
                'status' => ['required', 'in:Pending Verification,Active,For Review,Disconnected,For Reconnection'],
                'service_address' => ['nullable', 'string', 'max:255'],
                'contact_number' => AuthController::contactNumberRules(),
            ], AuthController::contactNumberMessages());

            if ($validator->fails()) {
                fclose($handle);

                return redirect()
                    ->route('consumers.index')
                    ->withErrors([
                        'consumer_csv' => "Row {$rowNumber}: ".$validator->errors()->first(),
                    ])
                    ->with('consumer_modal', 'bulk');
            }

            $normalizedAccountNumber = strtolower($mapped['account_number']);
            $normalizedMeterNumber = strtolower($mapped['meter_number']);

            if (isset($seenAccountNumbers[$normalizedAccountNumber])) {
                fclose($handle);

                return redirect()
                    ->route('consumers.index')
                    ->withErrors([
                        'consumer_csv' => "Row {$rowNumber}: duplicate account number in the uploaded CSV.",
                    ])
                    ->with('consumer_modal', 'bulk');
            }

            if (isset($seenMeterNumbers[$normalizedMeterNumber])) {
                fclose($handle);

                return redirect()
                    ->route('consumers.index')
                    ->withErrors([
                        'consumer_csv' => "Row {$rowNumber}: duplicate meter number in the uploaded CSV.",
                    ])
                    ->with('consumer_modal', 'bulk');
            }

            $seenAccountNumbers[$normalizedAccountNumber] = true;
            $seenMeterNumbers[$normalizedMeterNumber] = true;
            $rows[] = $mapped;
        }

        fclose($handle);

        if ($rows === []) {
            return redirect()
                ->route('consumers.index')
                ->withErrors(['consumer_csv' => 'The uploaded CSV does not contain any consumer rows.'])
                ->with('consumer_modal', 'bulk');
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                Consumer::create($row);
            }
        });

        return redirect()
            ->route('consumers.index')
            ->with('success', count($rows).' consumers imported successfully.');
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $templatePath = base_path('Untitled spreadsheet - Sheet1.csv');

        abort_unless(is_file($templatePath), 404, 'Consumer bulk upload template not found.');

        return response()->download($templatePath, 'consumer-bulk-upload-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Re-evaluates every account with an unpaid bill exactly once.
     */
    private function syncOpenBillStatuses(): void
    {
        $consumerIds = Bill::query()
            ->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])
            ->distinct()
            ->pluck('consumer_id')
            ->merge(DisconnectionNotice::query()->active()->pluck('consumer_id'))
            ->merge(\App\Models\Loan::query()->withExpiredUnpaidSchedule()->distinct()->pluck('consumer_id'))
            ->unique();

        if ($consumerIds->isEmpty()) {
            return;
        }

        Consumer::query()
            ->whereIn('id', $consumerIds)
            ->get()
            ->each(fn (Consumer $consumer) => $consumer->syncAccountStanding());
    }

    private function generateNextAccountNumber(): string
    {
        $next = Consumer::query()
            ->pluck('account_number')
            ->map(function ($accountNumber) {
                return preg_match('/^\d+$/', (string) $accountNumber) === 1
                    ? (int) $accountNumber
                    : 0;
            })
            ->max() + 1;

        return (string) max(1, $next);
    }

    private function bulkUploadColumns(): array
    {
        return [
            'Full Name',
            'Account Number',
            'Meter Number',
            'Membership Type',
            'Status',
            'Service Address',
            'Contact Number',
        ];
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    public function reject(Request $request, Consumer $consumer, ConnectionApplicationService $applications): RedirectResponse
    {
        $corrections = app(ConnectionApplicationCorrectionService::class);
        $options = $corrections->options();
        if (! $consumer->isMemberApplicant()) {
            unset($options[ConnectionApplicationCorrectionService::FIELD_MEMBERSHIP_DOCUMENTS]);
        }

        $validator = Validator::make($request->all(), [
            'requirements_reviewed' => [$consumer->hasSubmittedConnectionDocuments() || $consumer->status === Consumer::STATUS_PENDING_REQUIREMENTS ? 'accepted' : 'nullable'],
            'rejection_reason' => ['required', 'string', 'max:1000'],
            'correction_fields' => ['required', 'array', 'min:1'],
            'correction_fields.*' => ['string', Rule::in(array_keys($options))],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('consumers.index')
                ->withErrors($validator)
                ->withInput()
                ->with('consumer_modal', 'activate')
                ->with('consumer_activate_id', $consumer->id);
        }

        $validated = $validator->validated();

        $rejected = DB::transaction(function () use ($consumer, $request, $validated, $applications): bool {
            $consumer = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
            if (! $consumer->isAwaitingConnectionReview()) {
                return false;
            }

            $updates = [
                'status' => 'Rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'rejection_fields' => array_values(array_unique([...$validated['correction_fields'], ...$applications->requiredCorrections($consumer)])),
                'rejection_count' => ((int) $consumer->rejection_count) + 1,
                'rejected_at' => now(),
                'resubmitted_at' => null,
            ];

            if ($consumer->hasPendingMembershipVerification()) {
                $updates += [
                    'membership_type' => Consumer::MEMBERSHIP_REQUEST_NON_MEMBER,
                    'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_REJECTED,
                    'membership_reviewed_at' => now(),
                    'membership_reviewed_by' => $request->user()->id,
                    'membership_verification_notes' => $validated['rejection_reason'],
                ];
            }

            $consumer->update($updates);

            app(ConnectionRequestService::class)->reject(
                $consumer,
                $request->user()?->id,
                $validated['rejection_reason'],
            );

            return true;
        });

        if (! $rejected) {
            return redirect()->route('consumers.index')
                ->withErrors(['requirements' => 'This application is no longer awaiting a verification decision.']);
        }

        $emailResult = $applications->sendRejectionEmail($consumer->refresh());

        $response = redirect()
            ->route('consumers.index')
            ->with('success', 'Connection request has been rejected.');

        if ($emailResult === ConsumerNotificationLog::RESULT_FAILED) {
            $response = $response->with(
                'email_error',
                'The connection request was rejected, but the correction email could not be sent. Use "Re-send Correction Link" once the mail server is reachable.',
            );
        }

        return $response;
    }

    public function sendRequirementsLink(Consumer $consumer, ConnectionApplicationService $applications): RedirectResponse
    {
        $response = redirect()->back(fallback: route('consumers.index', ['account_category' => 'pending', 'pending_type' => 'applications']));

        if ($consumer->status !== Consumer::STATUS_PENDING_REQUIREMENTS) {
            return $response->withErrors(['requirements' => 'This application is no longer awaiting requirements.']);
        }

        if ($applications->sendRequirementsLink($consumer, force: true) === ConsumerNotificationLog::RESULT_SENT) {
            return $response->with('success', "The mail server accepted the requirements link email for {$consumer->email}.");
        }

        return $response->with('email_error', 'The requirements link email could not be sent. Check the application log for the cause.');
    }

    /**
     * Re-sends the rejection email with a fresh "Edit and Resubmit" link.
     *
     * Needed for applications rejected before the correction workflow existed,
     * or when the original email never reached the applicant.
     */
    public function resendCorrectionLink(Consumer $consumer, ConnectionApplicationService $applications): RedirectResponse
    {
        if ($consumer->status === Consumer::STATUS_PENDING_REQUIREMENTS) {
            return $this->sendRequirementsLink($consumer, $applications);
        }

        if ($consumer->status !== 'Rejected') {
            return redirect()
                ->route('consumers.index')
                ->withErrors(['resend' => 'Only rejected applications can receive a correction link.']);
        }

        if (blank($consumer->email)) {
            return redirect()
                ->route('consumers.index')
                ->withErrors(['resend' => 'This applicant has no email address on file.']);
        }

        $result = $applications->sendRejectionEmail($consumer, force: true);

        if ($result === ConsumerNotificationLog::RESULT_SENT) {
            return redirect()
                ->route('consumers.index')
                ->with('success', "Correction link sent to {$consumer->email}.");
        }

        return redirect()
            ->route('consumers.index')
            ->with(
                'email_error',
                'The correction link could not be emailed. Check storage/logs/laravel.log for the mail server error and try again.',
            );
    }

    public function destroy(Consumer $consumer): RedirectResponse
    {
        $consumer->delete();

        return redirect()
            ->route('consumers.index')
            ->with('success', 'Consumer deleted successfully.');
    }

    public function update(Request $request, Consumer $consumer): RedirectResponse
    {
        $noticeEdit = $request->routeIs('consumers.update-from-disconnection-notice');
        $redirect = $noticeEdit
            ? redirect()->back(fallback: route('consumers.index', ['account_category' => 'active']))
            : redirect()->route('consumers.index');
        $editErrors = fn ($errors) => $redirect->withErrors($errors)->withInput()->with([
            'consumer_modal' => 'edit',
            'consumer_edit_id' => $consumer->id,
            'consumer_notice_edit' => $noticeEdit,
        ]);

        $requestedEmail = trim((string) $request->input('email', ''));
        $emailRules = [$consumer->user_id ? 'required' : 'nullable', 'email', 'max:255'];
        if (mb_strtolower($requestedEmail) !== mb_strtolower(trim((string) $consumer->email))) {
            $emailRules[] = Rule::unique('consumers', 'email')->ignore($consumer->id);
            $emailRules[] = Rule::unique('users', 'email')->ignore($consumer->user_id);
        }
        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'account_number' => ['nullable', 'string', 'max:50', Rule::unique('consumers', 'account_number')->ignore($consumer->id)],
            'status' => [Rule::excludeIf($noticeEdit), 'required', Rule::in([
                'Pending Verification', 'Active', 'For Review', 'Disconnected', 'For Reconnection', 'Rejected',
                ...($consumer->status === Consumer::STATUS_PENDING_REQUIREMENTS ? [Consumer::STATUS_PENDING_REQUIREMENTS] : []),
            ])],
            'membership_type' => ['required', 'in:Member,Non-Member'],
            'membership_verification_notes' => ['nullable', 'string', 'max:1000'],
            'membership_verification_confirmed' => ['nullable', 'boolean'],
            'property_type' => ['nullable', 'in:Residential,Commercial'],
            'ownership_status' => ['nullable', 'in:Owner,Tenant'],
            'valid_id_presented' => ['nullable', 'string', 'max:100'],
            'housing_rights_documented' => ['nullable', 'boolean'],
            'resident_of_community' => ['nullable', 'boolean'],
            'association_member' => ['nullable', 'boolean'],
            'association_registered' => ['nullable', 'boolean'],
            'housing_loan_contract_submitted' => ['nullable', 'boolean'],
            'valid_id_submitted' => ['nullable', 'boolean'],
            'proof_of_residency_submitted' => ['nullable', 'boolean'],
            'other_document_details' => ['nullable', 'string', 'max:255'],
            'membership_renewal_date' => ['nullable', 'date'],
            'installation_date' => ['nullable', 'date'],
            'reconnection_date' => ['nullable', 'date'],
            'service_address' => ['nullable', 'string', 'max:255'],
            'address_block' => ['nullable', 'string', 'max:100'],
            'address_street' => ['nullable', 'string', 'max:100'],
            'contact_number' => AuthController::contactNumberRules(),
        ], AuthController::contactNumberMessages());

        if ($validator->fails()) {
            return $editErrors($validator);
        }

        $validated = $validator->validated();
        $requestedStatus = $validated['status'] ?? $consumer->status;

        // A pending application must be completed through Verify and Activate
        // so the account number, meter, membership decision, and connection
        // request are recorded together.
        if (in_array($consumer->status, Consumer::ACCOUNT_CATEGORY_STATUSES['pending'], true) && $requestedStatus !== $consumer->status) {
            $validator->errors()->add(
                'status',
                'Use Verify and Activate to approve this application so the account, meter, membership verification, and connection request stay in sync.',
            );

            return $editErrors($validator);
        }

        // A disconnected account keeps its status until the reconnection
        // request is reviewed. A profile edit must never quietly put it back
        // into service and bypass the association's authorised procedure.
        if (($consumer->reconnection_pending || in_array($consumer->status, Consumer::DISCONNECTION_ELIGIBLE_STATUSES, true))
            && $requestedStatus !== $consumer->status) {
            $validator->errors()->add(
                'status',
                'This account is awaiting reconnection and its status cannot be changed here. Review the reconnection request instead.',
            );

            return $editErrors($validator);
        }

        $membershipVerificationPending = $consumer->membership_verification_status === Consumer::MEMBERSHIP_VERIFICATION_PENDING;

        if ($membershipVerificationPending
            && ! $request->boolean('membership_verification_confirmed')) {
            $validator->errors()->add(
                'membership_verification_confirmed',
                'Confirm that you reviewed the existing VVTHOA membership and supporting documents before recording this membership decision.',
            );

            return $editErrors($validator);
        }

        $validated['housing_rights_documented'] = $request->boolean('housing_rights_documented');
        $validated['resident_of_community'] = $request->boolean('resident_of_community');
        $validated['association_member'] = $request->boolean('association_member');
        $validated['association_registered'] = $request->boolean('association_registered');
        $validated['housing_loan_contract_submitted'] = $request->boolean('housing_loan_contract_submitted');
        $validated['valid_id_submitted'] = $request->boolean('valid_id_submitted');
        $validated['proof_of_residency_submitted'] = $request->boolean('proof_of_residency_submitted');

        if ($validated['membership_type'] !== $consumer->membership_type || $membershipVerificationPending) {
            $validated = array_merge(
                $validated,
                $this->membershipDecisionAttributes(
                    $consumer,
                    $validated['membership_type'],
                    $validated['membership_verification_notes'] ?? null,
                    $request->user()->id,
                ),
            );
        }

        unset($validated['membership_verification_confirmed']);

        $newEmail = trim((string) ($validated['email'] ?? ''));
        $emailChanged = mb_strtolower($newEmail) !== mb_strtolower(trim((string) $consumer->email));
        if ($emailChanged && ! Schema::hasTable('consumer_email_verifications')) {
            return $editErrors(['email' => 'Email verification is unavailable until the database migration is applied.']);
        }
        $validated['email'] = $newEmail !== '' ? $newEmail : null;
        $verificationUrl = null;
        DB::transaction(function () use ($consumer, $validated, $emailChanged, $newEmail, &$verificationUrl): void {
            $locked = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
            $locked->update($validated);
            if ($emailChanged && $newEmail !== '') {
                if ($locked->user?->isConsumer()) {
                    $locked->user->forceFill(['email' => $newEmail, 'email_verified_at' => null])->save();
                }
                $verificationUrl = app(ConsumerEmailVerificationService::class)->prepare($locked);
            } elseif ($emailChanged) {
                DB::table('consumer_email_verifications')->where('consumer_id', $locked->id)->delete();
            }
        });

        if ($verificationUrl) {
            $result = app(ConsumerEmailVerificationService::class)->send($consumer->fresh(), $verificationUrl);
            if ($result !== ConsumerNotificationLog::RESULT_SENT) {
                return $redirect->with('email_error', 'The address was saved, but its verification email could not be sent. Use Resend Email Verification to retry.');
            }
            return $redirect->with('success', 'Consumer details saved. A verification link was sent to the new email address.');
        }

        return $redirect->with('success', 'Consumer details updated successfully.');
    }

    /**
 * Records a newly issued physical meter for a consumer.
 *
 * The consumer record is updated immediately so the meter reader module picks
 * up the new number, while the previous meter and its readings are preserved.
 */
public function assignMeter(Request $request, Consumer $consumer, MeterAssignmentService $meters): RedirectResponse
{
    abort_unless($request->user()->isAdmin(), 403);

    $returnToDss = $request->boolean('return_to_loans');
    $loanId = $request->integer('loan_id');
    $rules = [
        'meter_number' => ['required', 'string', 'max:50'],
        'reason' => ['nullable', 'string', 'max:255'],
    ];
    if ($returnToDss) {
        $rules['loan_id'] = ['required', 'integer', Rule::exists('loans', 'id')->where(fn ($query) => $query->where('consumer_id', $consumer->id))];
    }
    $validator = Validator::make($request->all(), $rules, [
        'meter_number.required' => 'Please enter the new meter number.',
    ]);
    if ($validator->fails()) {
        if ($returnToDss) {
            return redirect()->route('loans.index')->withErrors($validator)->withInput()->with('meter_assignment_loan_id', $loanId);
        }

        return redirect()->route('consumers.index')->withErrors($validator)->withInput()->with([
            'consumer_modal' => 'meter',
            'consumer_meter_id' => $consumer->id,
        ]);
    }
    $validated = $validator->validated();

    if ($returnToDss) {
        $loan = \App\Models\Loan::query()->with('latestDssEvaluation')->findOrFail($loanId);
        abort_unless($loan->latestDssEvaluation?->recommendation === 'Approved', 403);
    }

    $previous = $consumer->meter_number;

    try {
        $meters->assign($consumer, $validated['meter_number'], $request->user(), $validated['reason'] ?? null);
    } catch (\Illuminate\Validation\ValidationException $exception) {
        if ($returnToDss) {
            return redirect()->route('loans.index')->withErrors($exception->errors())->withInput()->with('meter_assignment_loan_id', $loanId);
        }

        return redirect()->route('consumers.index')->withErrors($exception->errors())->withInput()->with([
            'consumer_modal' => 'meter',
            'consumer_meter_id' => $consumer->id,
        ]);
    }

    if ($returnToDss) {
        return redirect()->route('loans.index')->with('success', 'New meter number assigned'.($previous ? " (previous meter {$previous} retained for auditing)." : '.'));
    }

    return redirect()
        ->route('consumers.index')
        ->with('success', 'New meter number assigned'.($previous ? " (previous meter {$previous} retained for auditing)." : '.'));
}

/** Mark a scheduled active-account installation complete once and audit the administrator. */
public function markInstallationComplete(Request $request, Consumer $consumer): RedirectResponse
{
    $scheduleUrl = route('consumers.index', array_merge($request->query(), ['account_category' => 'active', 'page' => 1]));
    $completed = DB::transaction(function () use ($request, $consumer): bool {
        $lockedConsumer = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);

        if ($lockedConsumer->reconnection_pending) {
            return false;
        }

        if ($lockedConsumer->installation_completed_at) {
            return true;
        }

        if (! in_array($lockedConsumer->status, ['Active', 'For Review'], true)
            || ! $lockedConsumer->installation_date) {
            return false;
        }

        $lockedConsumer->forceFill([
            'installation_completed_at' => now(),
            'installation_completed_by' => $request->user()->id,
        ])->save();

        return true;
    });

    if (! $completed) {
        return redirect()->to($scheduleUrl)->withErrors([
            'installation' => 'A finalized installation schedule is required before marking this installation complete.',
        ]);
    }

    return redirect()->to($scheduleUrl)->with('success', 'Installation marked as completed.');
}
/**
 * Re-sends the login details to an already-activated consumer.
 *
 * Sends a new single-use setup link while preserving the current password.
 */
public function resendCredentials(Request $request, Consumer $consumer, ConnectionApprovalEmailService $emails): RedirectResponse
{
    abort_unless($request->user()?->isAdmin(), 403);
    if (! $consumer->user?->isConsumer() || ! $consumer->isVerified() || blank($consumer->email)) {
        return redirect()->route('consumers.index')->withErrors(['resend' => 'An activated consumer account, meter number, and email address are required.']);
    }
    if (! Schema::hasTable('consumer_email_verifications') || DB::table('consumer_email_verifications')->where('consumer_id', $consumer->id)->whereNull('verified_at')->exists()) {
        return redirect()->route('consumers.index')->withErrors(['resend' => 'Verify the new email address before sending login details.']);
    }
    $result = $emails->send($consumer, $request, resend: true);
    $installationResult = $emails->sendInstallationConfirmation($consumer, $request);
    if ($result === ConsumerNotificationLog::RESULT_SENT) {
        $request->session()->forget('email_error');

        $response = redirect()->route('consumers.index')->with('success', 'Login details re-sent to '.$consumer->email.'.');
        if ($installationResult === ConsumerNotificationLog::RESULT_FAILED) {
            $response->with('email_error', 'The installation confirmation email could not be sent. Use Resend Login Details to retry.');
        }

        return $response;
    }
    return redirect()->route('consumers.index')->with('email_error', 'The login email could not be sent. The previous password remains valid; please retry.');
}

public function resendEmailVerification(Request $request, Consumer $consumer): RedirectResponse
{
    abort_unless($request->user()?->isAdmin(), 403);
    if (blank($consumer->email) || ! Schema::hasTable('consumer_email_verifications') || ! DB::table('consumer_email_verifications')->where('consumer_id', $consumer->id)->whereNull('verified_at')->exists()) {
        return redirect()->route('consumers.index')->withErrors(['email' => 'There is no pending email verification for this account.']);
    }
    $url = app(ConsumerEmailVerificationService::class)->prepare($consumer);
    $result = app(ConsumerEmailVerificationService::class)->send($consumer, $url);
    return redirect()->route('consumers.index')->with($result === ConsumerNotificationLog::RESULT_SENT ? 'success' : 'email_error', $result === ConsumerNotificationLog::RESULT_SENT ? 'A new verification link has been sent.' : 'The verification email could not be sent. Please retry.');
}

public function activate(Request $request, Consumer $consumer, ConnectionApprovalEmailService $emails): RedirectResponse
    {
        if ($consumer->status === Consumer::STATUS_PENDING_REQUIREMENTS) {
            return redirect()->route('consumers.index', ['account_category' => 'pending', 'pending_type' => 'applications'])
                ->withErrors(['requirements' => 'The applicant must submit the missing requirements before verification and activation.'])
                ->with('consumer_modal', 'none');
        }

        if (app(\App\Services\DisconnectedAccountService::class)->isRestricted($consumer)) {
            return redirect()->route('consumers.index', ['account_category' => 'inactive'])
                ->withErrors(['reconnection' => 'Use Reconnect under Inactive Accounts after recording and verifying the full required payment in Payments.']);
        }

        $validator = Validator::make($request->all(), [
            'requirements_reviewed' => [$consumer->hasSubmittedConnectionDocuments() && ! $consumer->isVerified() ? 'accepted' : 'nullable'],
            'meter_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('consumers', 'meter_number')->ignore($consumer->id),
            ],
            'membership_type' => ['required', 'in:Member,Non-Member'],
            'membership_verification_notes' => ['nullable', 'string', 'max:1000'],
            'membership_verification_confirmed' => ['nullable', 'boolean'],
            'installation_date' => ['required', 'date'],
            'installation_time' => ['required', 'date_format:H:i'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('consumers.index')
                ->withErrors($validator)
                ->withInput()
                ->with('consumer_modal', 'activate')
                ->with('consumer_activate_id', $consumer->id);
        }

        $validated = $validator->validated();

        if (blank($consumer->email)) {
            return redirect()
                ->route('consumers.index')
                ->withErrors(['activation_email' => 'The consumer must have a registered email before activation.'])
                ->withInput()
                ->with('consumer_modal', 'activate')
                ->with('consumer_activate_id', $consumer->id);
        }

        // Every Member activation requires an explicit verification decision.
        if ($validated['membership_type'] === Consumer::MEMBERSHIP_REQUEST_MEMBER
            && ! $request->boolean('membership_verification_confirmed')) {
            return redirect()
                ->route('consumers.index')
                ->withErrors([
                    'membership_verification_confirmed' => 'Please confirm that the applicant\'s association membership has been verified.',
                ])
                ->withInput()
                ->with('consumer_modal', 'activate')
                ->with('consumer_activate_id', $consumer->id);
        }

        $membershipDecision = $this->membershipDecisionAttributes(
            $consumer,
            $validated['membership_type'],
            $validated['membership_verification_notes'] ?? null,
            $request->user()?->id,
        );

        $connectionRequestNote = $validated['membership_type'] === Consumer::MEMBERSHIP_REQUEST_MEMBER
            ? 'Connection approved. VVTHOA membership verified.'
            : ($consumer->isMemberApplicant()
                ? 'Connection approved as Non-Member. Membership claim was not verified.'
                : 'Connection approved as Non-Member.');

        if (blank($consumer->email) || ! Schema::hasTable('consumer_email_verifications') || DB::table('consumer_email_verifications')->where('consumer_id', $consumer->id)->whereNull('verified_at')->exists()) {
            return redirect()->route('consumers.index')->withErrors(['email' => 'A verified email address is required before activation.'])->with('consumer_modal', 'activate')->with('consumer_activate_id', $consumer->id);
        }
        $notificationUser = DB::transaction(function () use ($consumer, $validated, $membershipDecision, $connectionRequestNote, $request, $emails): ?User {
            $consumer = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);
            if ($consumer->isVerified()) {
                return null;
            }
            if ($consumer->status === Consumer::STATUS_PENDING_REQUIREMENTS) {
                throw ValidationException::withMessages(['requirements' => 'The applicant must submit the missing requirements before approval.']);
            }
            if (! $consumer->isAwaitingConnectionReview()) {
                throw ValidationException::withMessages(['requirements' => 'This application is no longer awaiting a verification decision.']);
            }
            $user = $consumer->user;
            $temporaryPassword = Str::random(8);
            if ($user && ! $user->isConsumer()) {
                abort(422, 'The linked account is not a consumer account.');
            }
            if (! $user) {
                $user = User::create([
                    'name' => $consumer->full_name,
                    'username' => trim($validated['meter_number']),
                    'email' => $consumer->email,
                    'role' => 'consumer',
                    'password' => Hash::make($temporaryPassword),
                    'must_change_password' => true,
                ]);
            } else {
                app(ConsumerCredentialService::class)->applyTemporaryPassword($user, $temporaryPassword);
            }

            $consumer->forceFill([
                'user_id' => $user->id,
                'account_number' => $this->generateNextAccountNumber(),
                'meter_number' => $validated['meter_number'],
                'membership_type' => $validated['membership_type'],
                'status' => 'Active',
                'installation_date' => $validated['installation_date'],
                'installation_time' => $validated['installation_time'],
                'connection_approval_credentials' => [
                    'recipient' => trim($consumer->email),
                    'password' => $temporaryPassword,
                ],
            ] + $membershipDecision)->save();

            // Keep an existing linked consumer account aligned with the meter
            // number that was just approved.
            if ($user->isConsumer() && $user->username !== trim($validated['meter_number'])) {
                $user->forceFill(['username' => trim($validated['meter_number'])])->save();
            }

            app(ConnectionRequestService::class)->approve(
                $consumer,
                $request->user()?->id,
                $connectionRequestNote,
            );
            $emails->recordScheduledInstallation($consumer);

            return $user;
        });

        $consumer->refresh();
        $activationEmailResult = $emails->send($consumer, $request);
        $installationEmailResult = $emails->sendInstallationConfirmation($consumer, $request);
        if ($notificationUser) {
            $notificationUser->notify(new ConnectionStatusNotification(
                $consumer,
                'Connection Request Approved',
                'Your water connection request has been approved. Your account number is '.$consumer->account_number.', installation is scheduled for '.$consumer->installation_date->format('F d, Y').'.'
            ));
        }

        $response = redirect()->route('consumers.index');

        if ($installationEmailResult === ConsumerNotificationLog::RESULT_FAILED) {
            $message = $activationEmailResult === ConsumerNotificationLog::RESULT_FAILED
                ? 'The account was activated, but the approval and installation confirmation emails could not be sent.'
                : 'The application was approved and scheduled, but the installation confirmation email could not be sent.';

            return $response->with('email_error', $message.' Use Resend Login Details on the active account to retry.');
        }

        if ($activationEmailResult === ConsumerNotificationLog::RESULT_SENT) {
            $request->session()->forget('email_error');

            return $response->with(
                'success',
                'Connection request approved successfully. The account details email has been sent.'
                    .($installationEmailResult === ConsumerNotificationLog::RESULT_SENT ? ' The installation confirmation email has been sent.' : '')
            );
        }

        if ($activationEmailResult === ConsumerNotificationLog::RESULT_SUPPRESSED) {
            // Details were already delivered earlier, so nothing was re-sent.
            return $response->with(
                'success',
                'Connection request approved successfully. The account details email was already sent previously and was not repeated.'
            );
        }

        return $response->with(
            'email_error',
            'The account was activated, but the approval email was not sent.'
                .' Use Resend Login Details on the active account to retry.'
        );
    }

    /**
     * Builds the auditable membership verification fields for a decision.
     *
     * @return array<string, mixed>
     */
    private function membershipDecisionAttributes(
        Consumer $consumer,
        string $membershipType,
        ?string $notes,
        ?int $reviewerId,
    ): array {
        $notes = filled($notes) ? trim($notes) : null;

        if ($membershipType === Consumer::MEMBERSHIP_REQUEST_MEMBER) {
            return [
                'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_VERIFIED,
                'membership_reviewed_at' => now(),
                'membership_reviewed_by' => $reviewerId,
                'membership_verification_notes' => $notes,
            ];
        }

        if ($consumer->isMemberApplicant()) {
            return [
                'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_REJECTED,
                'membership_reviewed_at' => now(),
                'membership_reviewed_by' => $reviewerId,
                'membership_verification_notes' => $notes ?: 'Membership claim was not verified.',
            ];
        }

        return [
            'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_NOT_REQUIRED,
            'membership_reviewed_at' => null,
            'membership_reviewed_by' => null,
            'membership_verification_notes' => $notes,
        ];
    }
}







