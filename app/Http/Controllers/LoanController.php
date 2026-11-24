<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\InstallationRescheduleRequest;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Services\ConsumerEmailUrl;
use App\Services\DisconnectedAccountService;
use App\Services\InstallationRescheduleService;
use App\Rules\FileMimeType;
use App\Rules\ReadableIncomeProof;
use App\Notifications\LoanStatusNotification;
use App\Services\LoanIncomeVerificationService;
use App\Services\LoanIncomeProofResubmissionService;
use App\Services\LoanRepaymentAllocationService;
use App\Services\DssEligibilityService;
use App\Services\LoanIncomeOcrService;
use App\Services\LoanInstallationCompletionService;
use App\Services\LoanInstallationEmailService;
use App\Services\LoanMaterialRateService;
use App\Services\LoanPaymentBreakdownService;
use App\Services\MeterAssignmentService;
use App\Services\RagStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function index(
        Request $request,
        DssEligibilityService $dss,
        LoanMaterialRateService $materialRates,
    ): View|RedirectResponse
    {
        $user = $request->user();

        $consumerIsMember = $user->consumer
            && ($user->consumer->membership_type === Consumer::MEMBERSHIP_REQUEST_MEMBER
                || $user->consumer->requested_membership_type === Consumer::MEMBERSHIP_REQUEST_MEMBER);

        if ($user->isConsumer() && ! $consumerIsMember) {
            $message = $user->consumer?->hasPendingMembershipVerification()
                ? DssEligibilityService::MEMBERSHIP_PENDING_MESSAGE
                : DssEligibilityService::MEMBERS_ONLY_MESSAGE;

            return redirect()
                ->route('dashboard')
                ->with('loan_access_denied', $message);
        }

        if ($user->isConsumer()) {
            $user->consumer?->syncAccountStanding();
        }

        $status = trim((string) $request->string('status'));
        $status = in_array($status, ['Pending Review', 'Approved', 'Rejected'], true) ? $status : '';
        $risk = trim((string) $request->string('risk'));
        $search = trim((string) $request->string('search'));
        $loansTableExists = Schema::hasTable('loans');
        $repaymentsTableExists = Schema::hasTable('loan_repayments');
        $installmentsTableExists = Schema::hasTable('loan_installments');

        $loanTab = (string) $request->query('tab', 'all');
        $loanTab = in_array($loanTab, ['all', 'pending', 'costs'], true) ? $loanTab : 'all';
        $pendingTab = (string) $request->query('pending', 'income');
        $pendingTab = in_array($pendingTab, ['income', 'reschedule'], true) ? $pendingTab : 'income';
        if ($user->isAdmin() && $loansTableExists && Schema::hasTable('loan_dss_evaluations')) {
            app(LoanIncomeVerificationService::class)->reconcilePreviouslyVerified();
        }
        $applicationStatus = (string) $request->query('application_status', 'all');
        $applicationStatus = in_array($applicationStatus, ['all', 'approved', 'rejected'], true) ? $applicationStatus : 'all';
        $applicationSearch = trim((string) $request->query('application_search', ''));
        $installationStatus = $request->query('installation_status');
        $installationStatus = in_array($installationStatus, ['pending', 'completed'], true) ? $installationStatus : 'all';
        $focusedLoanId = max(0, $request->integer('loan'));
        $adminApplications = $user->isAdmin() && $loansTableExists
            ? Loan::query()->with(['consumer', 'incomeVerifier', 'latestDssEvaluation', 'agreement:id,loan_id,issued_at,email_status'])
                ->withExists(['installationRescheduleRequests as has_pending_installation_reschedule' => fn ($query) => $query->pending()])
                ->when($focusedLoanId, fn ($query) => $query->whereKey($focusedLoanId))
                ->when($applicationStatus !== 'all', function ($query) use ($applicationStatus): void {
                    $category = $applicationStatus === 'pending' ? 'Pending Review' : ucfirst($applicationStatus);
                    $query->where(function ($items) use ($category): void {
                        if ($category !== 'Pending Review') {
                            $items->whereHas('latestDssEvaluation', fn ($evaluation) => $evaluation->where('recommendation', $category));
                        }
                        $items->orWhere(function ($fallback) use ($category): void {
                            $fallback->whereDoesntHave('latestDssEvaluation', fn ($evaluation) => $evaluation->whereIn('recommendation', ['Approved', 'Rejected']))
                                ->applicationCategory($category);
                        });
                    });
                })
                ->when($applicationSearch !== '', fn ($query) => $query->whereHas('consumer', fn ($consumer) => $consumer->where(fn ($terms) => $terms->where('full_name', 'like', '%'.$applicationSearch.'%')->orWhere('account_number', 'like', '%'.$applicationSearch.'%'))))
                ->when($installationStatus === 'pending', fn ($query) => $query->whereNotNull('installation_date')
                    ->where('installation_status', '!=', 'Completed'))
                ->when($installationStatus === 'completed', fn ($query) => $query->where('installation_status', 'Completed'))
                ->orderByDesc('applied_at')->orderByDesc('id')->paginate(10, ['*'], 'application_page')->withQueryString()
            : $this->emptyPaginator($request);
        $retryableInstallationEmails = $adminApplications->isNotEmpty()
            ? ConsumerNotificationLog::query()
                ->whereIn('event', $adminApplications->getCollection()->map->initialInstallationEmailEvent())
                ->whereIn('status', [ConsumerNotificationLog::STATUS_PENDING, ConsumerNotificationLog::STATUS_FAILED])
                ->pluck('consumer_id', 'event')
            : collect();

        $loans = $loansTableExists && $user->isConsumer()
            ? Loan::query()
                ->with(['consumer', 'latestDssEvaluation', 'agreement:id,loan_id,issued_at'])
                ->when($repaymentsTableExists, fn ($query) => $query->withSum('repayments', 'amount'))
                ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
                ->when($status !== '', fn ($query) => $query->applicationCategory($status))
                ->when($risk !== '', fn ($query) => $query->where('risk_level', $risk))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($loanQuery) use ($search) {
                        $loanQuery
                            ->where('purpose', 'like', "%{$search}%")
                            ->orWhereHas('consumer', function ($consumerQuery) use ($search) {
                                $consumerQuery
                                    ->where('full_name', 'like', "%{$search}%")
                                    ->orWhere('account_number', 'like', "%{$search}%");
                            });
                    });
                })
                ->orderByDesc('applied_at')
                ->paginate(10)
                ->withQueryString()
            : $this->emptyPaginator($request);

        $rag = app(RagStatusService::class);
        $loanRag = [];
        foreach ($loans as $loan) {
            $loanRag[$loan->id] = $rag->loanHealth($loan);
        }

        $currentEligibility = $user->isConsumer()
            ? $dss->loanEligibility($user->consumer)
            : null;
        $activeLoanMaterialRate = $materialRates->activeRate();
        $loanCostBreakdown = $materialRates->breakdown($activeLoanMaterialRate);
        $loanCostsAvailable = $materialRates->costsAreAvailable($loanCostBreakdown);
        $pendingIncomeVerifications = $user->isAdmin() && $loansTableExists
            ? Loan::query()
                ->with(['consumer', 'latestDssEvaluation'])
                ->when($focusedLoanId, fn ($query) => $query->whereKey($focusedLoanId))
                ->where(function ($query): void {
                    $query->whereIn('status', Loan::PENDING_STATUSES)
                        ->orWhere(function ($reviewQuery): void {
                            $reviewQuery->where('status', 'Rejected')->whereNotNull('review_requested_at');
                        });
                })
                ->whereNotNull('applied_at')
                ->where(function ($query): void {
                    $query->whereNull('income_verification_status')->orWhereIn('income_verification_status', ['Pending Verification', 'Needs Review', 'Verified']);
                })
                ->orderByDesc('applied_at')
                ->paginate(10, ['*'], 'verification_page')
                ->withQueryString()
            : $this->emptyPaginator($request);
        $hasBlockingLoan = $user->isConsumer() && $user->consumer
            ? $user->consumer->loans()->pendingReview()->exists()
            : false;
        $canApplyForLoan = $user->isConsumer()
            && $consumerIsMember
            && ! $hasBlockingLoan
            && $loanCostsAvailable;
        $showLoanIneligibleModal = $user->isConsumer()
            && $currentEligibility
            && ! ($currentEligibility['eligible'] ?? false)
            && ! $hasBlockingLoan;
        $failedEligibilityReasons = $currentEligibility
            ? collect($currentEligibility['reasons'])->where('passed', false)->values()
            : collect();
        $scheduledInstallations = $loansTableExists
            ? $this->scheduledInstallations($user, $repaymentsTableExists)
            : collect();
        $scheduledInstallations->each(function (Loan $loan) use ($user, $installmentsTableExists): void {
            $loan->setAttribute('billing_readiness', $this->loanBillingReadiness($loan, $user->isAdmin(), $installmentsTableExists));
        });

        return view($this->roleViewPath($request, 'loans.index'), [
            'loanTab' => $loanTab,
            'pendingTab' => $pendingTab,
            'applicationStatus' => $applicationStatus,
            'applicationSearch' => $applicationSearch,
            'installationStatus' => $installationStatus,
            'focusedLoanId' => $focusedLoanId,
            'adminApplications' => $adminApplications,
            'retryableInstallationEmails' => $retryableInstallationEmails,
            'pendingLoanReschedules' => $user->isAdmin() && $loansTableExists
                ? InstallationRescheduleRequest::query()->pending()->where('reschedule_type', InstallationRescheduleRequest::TYPE_LOAN)
                    ->with(['consumer', 'loan.latestDssEvaluation'])->orderByDesc('id')->get()
                    ->filter(fn (InstallationRescheduleRequest $reschedule) => $reschedule->loan?->isApprovedForInstallation())
                : collect(),
            'loans' => $loans,
            'loanRag' => $loanRag,
            'statuses' => ['Pending Review', 'Approved', 'Rejected'],
            'reviewStatuses' => Loan::REVIEW_STATUSES,
            'installationStatuses' => Loan::INSTALLATION_STATUSES,
            'riskLevels' => ['Low Risk', 'Medium Risk', 'High Risk'],
            'filters' => [
                'status' => $status,
                'risk' => $risk,
                'search' => $search,
            ],
            'stats' => $loansTableExists ? $this->buildStats($user, $dss) : [
                'approved' => 0,
                'pending' => 0,
                'high_risk' => 0,
                'due_this_week' => 0,
                'eligible_consumers' => 0,
                'not_eligible_consumers' => 0,
                'no_record_consumers' => 0,
            ],
            'recommendations' => $loansTableExists ? $this->recommendationFeed($user) : collect(),
            'scheduledInstallations' => $scheduledInstallations,
            'pendingIncomeVerifications' => $pendingIncomeVerifications,
            'loanInstallments' => ($loansTableExists && $installmentsTableExists) ? $this->loanInstallments($user) : collect(),
            'repaymentSchedule' => $loansTableExists ? $this->repaymentSchedule($user) : collect(),
            'recentRepayments' => ($loansTableExists && $repaymentsTableExists) ? $this->recentRepayments($user) : collect(),
            'consumers' => $user->isAdmin()
                ? Consumer::query()->whereIn('status', ['Active', 'Verified'])->orderBy('account_number')->get()
                : collect(),
            'canManageLoans' => $user->isAdmin(),
            'currentEligibility' => $currentEligibility,
            'failedEligibilityReasons' => $failedEligibilityReasons,
            'activeLoanMaterialRate' => $activeLoanMaterialRate,
            'loanCostBreakdown' => $loanCostBreakdown,
            'loanCostsAvailable' => $loanCostsAvailable,
            'hasBlockingLoan' => $hasBlockingLoan,
            'canApplyForLoan' => $canApplyForLoan,
            'showLoanIneligibleModal' => $showLoanIneligibleModal,
            'loanInstallmentSchedules' => $materialRates->schedules($loanCostBreakdown['total_installation_cost']),
            'loansTableExists' => $loansTableExists,
            'repaymentsTableExists' => $repaymentsTableExists,
            'installmentsTableExists' => $installmentsTableExists,
        ]);
    }

    public function confirmSubmission(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->isConsumer() && $request->user()->consumer, 403);

        $validated = $request->validate([
            'terms_accepted' => ['accepted'],
            'confirmed' => ['accepted'],
            'term_months' => ['required', 'integer', 'in:3,6,12'],
        ]);

        $token = bin2hex(random_bytes(32));
        $request->session()->put('loan_submission_confirmation', [
            'token_hash' => hash('sha256', $token),
            'user_id' => $request->user()->id,
            'term_months' => (int) $validated['term_months'],
            'version' => \App\Support\LoanTerms::VERSION,
            'confirmed_at' => now()->timestamp,
        ]);

        return response()->json(['confirmation_token' => $token])->header('Cache-Control', 'no-store');
    }

    public function store(
        Request $request,
        DssEligibilityService $dss,
        LoanMaterialRateService $materialRates,
        LoanIncomeOcrService $incomeOcr,
    ): RedirectResponse
    {
        if (! Schema::hasTable('loans')) {
            return redirect()
                ->route('loans.index')
                ->withErrors([
                    'loans' => 'The loans module is not ready yet. Run php artisan migrate first.',
                ]);
        }

        $user = $request->user();

        if (! $user->isConsumer()) {
            return redirect()
                ->route('loans.index')
                ->withErrors([
                    'loan' => 'Only consumers can submit loan applications.',
                ]);
        }

        $consumer = $user->consumer;

        abort_if(! $consumer, 403);

        if (! in_array(Consumer::MEMBERSHIP_REQUEST_MEMBER, [
            $consumer->membership_type,
            $consumer->requested_membership_type,
        ], true)) {
            return redirect()
                ->route('dashboard')
                ->with('loan_access_denied', DssEligibilityService::MEMBERS_ONLY_MESSAGE);
        }

        $validated = $request->validate([
            'proof_of_income' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:10240', new FileMimeType('jpg,jpeg,png,pdf'), new ReadableIncomeProof],
            'term_months' => ['required', 'integer', 'in:3,6,12'],
            'purpose' => ['required', 'string', 'max:1000'],
            'terms_accepted' => ['accepted'],
            'confirmation_token' => ['required', 'string', 'size:64'],
        ]);

        $confirmation = $request->session()->pull('loan_submission_confirmation');
        if (! is_array($confirmation)
            || (int) ($confirmation['user_id'] ?? 0) !== (int) $user->id
            || (int) ($confirmation['term_months'] ?? 0) !== (int) $validated['term_months']
            || ($confirmation['version'] ?? null) !== \App\Support\LoanTerms::VERSION
            || (int) ($confirmation['confirmed_at'] ?? 0) < now()->subMinutes(10)->timestamp
            || ! hash_equals((string) ($confirmation['token_hash'] ?? ''), hash('sha256', $validated['confirmation_token']))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'confirmation_token' => 'Please accept the terms and confirm the application before submitting.',
            ]);
        }

        if ($consumer->loans()->pendingReview()->exists()) {
            return redirect()
                ->route('loans.index')
                ->withErrors([
                    'loan' => 'You already have a pending loan application. Wait for its review before submitting another.',
                ]);
        }

        $snapshot = $materialRates->snapshotForTerm((int) $validated['term_months']);
        if (! $materialRates->costsAreAvailable($snapshot)) {
            return redirect()
                ->route('loans.index')
                ->withErrors([
                    'loan_material_rates' => 'The installation cost is currently unavailable. Please contact the administrator.',
                ]);
        }

        $termMonths = (int) $validated['term_months'];
        $amount = (float) $snapshot['total_installation_cost'];
        $monthlyInstallment = (float) $snapshot['regular_monthly_installment'];
        $proof = $validated['proof_of_income'];
        // OCR is supplementary only; eligibility uses administrator-verified income.
        $ocrEvidence = strtolower($proof->getClientOriginalExtension()) === 'pdf'
            ? []
            : $incomeOcr->extract($proof);
        $proofPath = $proof->store('loan-income-proofs', 'local');

        try {
            $loan = DB::transaction(function () use ($consumer, $user, $validated, $termMonths, $amount, $monthlyInstallment, $snapshot, $ocrEvidence, $proofPath): Loan {
                Consumer::query()->whereKey($consumer->id)->lockForUpdate()->firstOrFail();
                if ($consumer->loans()->pendingReview()->lockForUpdate()->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'loan' => 'You already have a pending loan application. Wait for its review before submitting another.',
                    ]);
                }

                $loan = Loan::create([
                    'consumer_id' => $consumer->id,
                    'applied_by_user_id' => $user->id,
                    'amount' => $amount,
                    'proof_of_income_path' => $proofPath,
                    'income_ocr_data' => $ocrEvidence,
                    'income_verification_status' => 'Pending Verification',
                    'term_months' => $termMonths,
                    'purpose' => $validated['purpose'],
                    'terms_agreed_at' => now(),
                    'submission_confirmed_at' => now(),
                    'terms_version' => \App\Support\LoanTerms::VERSION,
                    'terms_text' => \App\Support\LoanTerms::text($termMonths),
                    'material_meter_cost' => $snapshot['meter'] ?? null,
                    'material_hose_cost' => $snapshot['hose'] ?? null,
                    'material_other_cost' => $snapshot['other_materials'] ?? null,
                    'installation_cost_total' => $snapshot['total_installation_cost'] ?? null,
                    'installment_schedule_snapshot' => $snapshot['schedule'] ?? null,
                    'status' => 'Needs Review',
                    'review_status' => 'Pending Review',
                    'installation_status' => 'Not Scheduled',
                    'recommendation_notes' => 'Income proof is awaiting trusted source verification and DSS review.',
                    'monthly_installment' => $monthlyInstallment,
                    'applied_at' => now(),
                ]);

                return $loan;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($proofPath);
            throw $exception;
        }

        if (Schema::hasTable('notifications')) {
            $this->notifyAdminsOfNewLoanApplication($loan->load('consumer'));
        }

        return redirect()
            ->route('loans.index')
            ->with('success', 'Your loan application was received and is marked Needs Review while staff verifies the proof of income.');
    }

    public function replaceIncomeProof(Request $request, Loan $loan, LoanIncomeProofResubmissionService $resubmission): RedirectResponse
    {
        abort_unless($request->user()->isConsumer() && $loan->consumer?->user_id === $request->user()->id, 403);
        abort_unless(in_array($loan->status, Loan::PENDING_STATUSES, true) && $loan->income_verification_status === 'Rejected', 404);
        $validated = $request->validate([
            'proof_of_income' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:10240', new FileMimeType('jpg,jpeg,png,pdf'), new ReadableIncomeProof],
        ]);
        $resubmission->replace($loan, $validated['proof_of_income']);

        return redirect()->route('loans.index')->with('success', 'Replacement proof of income submitted for administrator review.');
    }

    public function updateMaterialRates(Request $request, LoanMaterialRateService $materialRates): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'water_meter_cost' => ['required', 'numeric', 'min:0'],
            'hose_cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'other_materials_cost' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $materialRates->updateActiveRate($validated, $request->user());
        } catch (ValidationException $exception) {
            return redirect()
                ->route('loans.index', ['tab' => 'costs'])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('loans.index', ['tab' => 'costs'])
            ->with('success', 'Installation material costs updated successfully.');
    }

    public function paymentBreakdown(Request $request, Loan $loan, LoanPaymentBreakdownService $breakdowns)
    {
        abort_unless($request->user()->isConsumer() && $loan->consumer?->user_id === $request->user()->id, 403);

        return response()->view('consumer.loans.payment-breakdown', [
            'loan' => $loan,
            'breakdown' => $breakdowns->forLoan($loan),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function incomeProof(Request $request, Loan $loan)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($user->isConsumer() && $loan->consumer?->user_id === $user->id), 403);
        abort_unless(filled($loan->proof_of_income_path) && Storage::disk('local')->exists($loan->proof_of_income_path), 404);

        $path = Storage::disk('local')->path($loan->proof_of_income_path);
        $mime = Storage::disk('local')->mimeType($loan->proof_of_income_path) ?: 'application/octet-stream';
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true), 404);

        return response()->file($path, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function showIncomeVerification(Loan $loan): RedirectResponse
    {
        $pending = $loan->applied_at && in_array($loan->status, Loan::PENDING_STATUSES, true)
            && in_array($loan->income_verification_status, [null, 'Pending Verification', 'Needs Review'], true);

        if ($pending && filled($loan->proof_of_income_path) && Storage::disk('local')->exists($loan->proof_of_income_path)) {
            return redirect()->route('loans.index', ['tab' => 'pending', 'pending' => 'income', 'loan' => $loan->id]);
        }

        $message = match ($loan->income_verification_status) {
            'Rejected' => 'Proof of income for loan #'.$loan->id.' needs correction. The consumer must upload a replacement before it can be verified.',
            'Verified' => 'Proof of income for loan #'.$loan->id.' has already been verified.',
            default => $pending
                ? 'The proof of income for loan #'.$loan->id.' is unavailable. A document is required for verification.'
                : 'Loan #'.$loan->id.' is not pending income verification.',
        };

        return redirect()->route('loans.index', ['tab' => 'all', 'loan' => $loan->id])
            ->with('income_verification_message', $message);
    }

    public function verifyIncome(Request $request, Loan $loan, LoanIncomeVerificationService $verification): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $decision = $request->input('decision', 'confirm');
        $request->validate(['decision' => ['sometimes', 'in:confirm,reject']]);

        if ($decision === 'reject') {
            $validated = $request->validate([
                'rejection_reason' => ['required', 'string', 'min:8', 'max:2000'],
            ]);
            $verification->reject($loan, $request->user(), $validated['rejection_reason']);

            return redirect()->route('loans.index', ['tab' => 'pending', 'pending' => 'income'])
                ->with('success', 'Proof of income rejected and reason recorded.');
        }

        $validated = $request->validate([
            'identity_confirmed' => ['sometimes', 'in:1'],
            'document_authentic' => ['sometimes', 'in:1'],
            'income_source_verified' => ['sometimes', 'in:1'],
            'verified_monthly_income' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'verification_notes' => ['sometimes', 'string', 'min:8', 'max:2000'],
        ]);
        $decision = $verification->confirm($loan, $request->user(), $validated);

        return redirect()->route('loans.index', ['tab' => 'pending', 'pending' => 'income'])
            ->with('success', $this->dssDecisionMessage($decision));
    }

    public function evaluateVerifiedIncome(Request $request, Loan $loan, LoanIncomeVerificationService $verification): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $decision = $verification->evaluateVerified($loan);

        return redirect()->route('loans.index', ['tab' => 'pending', 'pending' => 'income'])
            ->with('success', $this->dssDecisionMessage($decision));
    }

    private function dssDecisionMessage(string $decision): string
    {
        return match ($decision) {
            'Approved' => 'Income verified. DSS approved the application and moved it to Ready for Scheduling.',
            'Rejected' => 'Income verified. DSS rejected the application; the decision and reasons have been recorded.',
            default => 'Income verified. DSS needs more information; the application remains pending review.',
        };
    }

    public function requestReview(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless($request->user()->isConsumer() && $loan->consumer?->user_id === $request->user()->id, 403);
        abort_unless($loan->status === 'Rejected' && $loan->review_requested_at === null, 404);

        $validated = $request->validate([
            'review_request_reason' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        $loan->update([
            'review_requested_at' => now(),
            'review_request_reason' => trim($validated['review_request_reason']),
        ]);

        User::query()->where('role', 'admin')->get()->each(fn (User $admin) => $admin->notify(new LoanStatusNotification(
            loan: $loan->load('consumer'),
            title: 'Loan decision review requested',
            message: $loan->consumer->full_name.' requested a review of a rejected loan application.',
            extraData: ['review_request_reason' => $loan->review_request_reason],
        )));

        return redirect()->route('loans.index')->with('success', 'Your review request has been sent to the Admin.');
    }

    public function update(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'review_status' => ['required', 'in:'.implode(',', Loan::REVIEW_STATUSES)],
            'next_due_date' => ['nullable', 'date'],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array($loan->status, Loan::PENDING_STATUSES, true)
            && $validated['review_status'] !== 'Pending Review'
            && $loan->income_verification_status !== 'Verified') {
            return redirect()->route('loans.index')
                ->withErrors(['review_status' => 'Income verification must be completed before the application can proceed.']);
        }

        if ($validated['review_status'] === 'Active'
            && ($loan->latestDssEvaluation?->recommendation !== 'Approved' || $loan->status !== 'Approved')) {
            return redirect()
                ->route('loans.index')
                ->withErrors(['review_status' => 'This application must pass the trusted income verification and DSS workflow before scheduling.']);
        }

        $updates = [
            'review_status' => $validated['review_status'],
        ];

        if ($request->has('review_notes')) {
            $updates['review_notes'] = $validated['review_notes'] ?? null;
        }

        if ($validated['review_status'] === 'Active') {
            $updates['status'] = 'Approved';
            $updates['approved_at'] = $loan->approved_at ?? now();
            $updates['decision_at'] = $loan->decision_at ?? now();
            $updates['next_due_date'] = $validated['next_due_date'] ?? $loan->next_due_date ?? now()->addMonth()->toDateString();
            $updates['start_billing_period'] = $loan->start_billing_period ?? now()->startOfMonth()->addMonth()->toDateString();
        }

        if ($validated['review_status'] === 'Completed') {
            $updates['status'] = 'Completed';
            $updates['next_due_date'] = null;
        }

        $previousStatus = $loan->review_status ?? 'Pending Review';
        $loan->update($updates);

        if (Schema::hasTable('notifications') && $loan->consumer?->user) {
            $message = $previousStatus !== $loan->review_status
                ? sprintf(
                    'Your loan review status changed from %s to %s.',
                    $previousStatus,
                    $loan->review_status,
                )
                : 'Your loan application review was updated by the admin review team.';

            $this->notifyConsumerOfLoanUpdate(
                $loan->load('consumer.user'),
                'Loan review updated',
                $message,
            );
        }

        return redirect()
            ->route('loans.index')
            ->with('success', 'Loan review saved successfully.');
    }

    public function assignAndSchedule(
        Request $request,
        Loan $loan,
        MeterAssignmentService $meters,
        LoanInstallationEmailService $emails,
    ): JsonResponse {
        abort_unless($request->user()->isAdmin(), 403);
        $today = today(Loan::INSTALLATION_TIMEZONE)->toDateString();
        $data = $request->validate([
            'meter_number' => ['required', 'string', 'max:50'],
            'installation_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'installation_time' => ['required', 'date_format:H:i'],
            'installation_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $scheduled = DB::transaction(function () use ($loan, $request, $data, $meters, $emails): Loan {
            $consumer = Consumer::query()->lockForUpdate()->findOrFail($loan->consumer_id);
            $locked = Loan::query()->with('latestDssEvaluation')->lockForUpdate()->findOrFail($loan->id);
            if (! $this->loanEligibleForInstallation($locked) || ! $this->loanHasConfiguredInstallationCost($locked) || $locked->installation_date || $locked->installationRescheduleRequests()->pending()->exists()) {
                throw ValidationException::withMessages(['installation' => 'This application is not available for initial scheduling.']);
            }
            if (! $consumer->user || blank($consumer->email) || app(DisconnectedAccountService::class)->isRestricted($consumer)) {
                throw ValidationException::withMessages(['installation' => 'An active consumer account and registered email are required.']);
            }
            $meters->assign($consumer, $data['meter_number'], $request->user(), 'Loan installation');
            $locked->update([
                'installation_status' => 'Scheduled', 'review_status' => 'Installation Scheduled',
                'installation_date' => $data['installation_date'], 'installation_time' => $data['installation_time'],
                'installation_notes' => $data['installation_notes'] ?? null,
            ]);
            $emails->recordPending($locked, $consumer);

            return $locked;
        });

        if ($emails->send($scheduled) === ConsumerNotificationLog::RESULT_FAILED) {
            $request->session()->flash('email_error', 'Meter and installation schedule saved. Use Retry Installation Email to send the confirmation again.');
        } else {
            $request->session()->flash('success', 'Meter and installation schedule saved. The confirmation email has been sent.');
        }

        return response()->json(['success' => true]);
    }

    public function retryInstallationEmail(Loan $loan, LoanInstallationEmailService $emails): RedirectResponse
    {
        return match ($emails->send($loan)) {
            ConsumerNotificationLog::RESULT_FAILED => back()->with('email_error', 'The installation confirmation email could not be sent. You can retry from the loan row.'),
            ConsumerNotificationLog::RESULT_SUPPRESSED => back()->with('success', 'The installation confirmation email was already sent.'),
            default => back()->with('success', 'The installation confirmation email has been sent.'),
        };
    }

    public function updateInstallationSchedule(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($loan->installationRescheduleRequests()->pending()->exists()) {
            return redirect()->route('loans.index')->withErrors(['installation' => 'Resolve the pending rescheduling request before changing the schedule.']);
        }

        $loan->loadMissing('latestDssEvaluation');

        if (! $this->loanEligibleForInstallation($loan)) {
            return redirect()
                ->route($this->workflowReturnRoute($request))
                ->withErrors(['installation' => 'Only reviewed eligible loan applications can be scheduled for installation.']);
        }

        if (! $this->loanHasConfiguredInstallationCost($loan)) {
            return redirect()
                ->route($this->workflowReturnRoute($request))
                ->withErrors(['installation' => 'The installation cost is currently unavailable. Please contact the administrator.']);
        }

        $validated = $request->validate([
            'installation_date' => ['required', 'date'],
            'installation_time' => ['required', 'date_format:H:i'],
            'installation_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $originalSchedule = [
            'installation_date' => $loan->installation_date?->toDateString(),
            'installation_time' => $loan->installation_time ? substr((string) $loan->installation_time, 0, 5) : null,
            'installation_notes' => $loan->installation_notes,
        ];
        $newSchedule = [
            'installation_date' => $validated['installation_date'],
            'installation_time' => $validated['installation_time'],
            'installation_notes' => $validated['installation_notes'] ?? null,
        ];
        $scheduleChanged = $originalSchedule !== $newSchedule;
        $isNewSchedule = blank($originalSchedule['installation_date']) || blank($originalSchedule['installation_time']);

        DB::transaction(function () use ($loan, $newSchedule, $scheduleChanged, $isNewSchedule): void {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            if ($loan->installation_status === 'Completed') {
                throw ValidationException::withMessages(['installation' => 'This installation has already been completed.']);
            }
            $loan->update([
                'review_status' => 'Installation Scheduled',
                'installation_status' => 'Scheduled',
                'installation_date' => $newSchedule['installation_date'],
                'installation_time' => $newSchedule['installation_time'],
                'installation_notes' => $newSchedule['installation_notes'],
            ]);

            if ($scheduleChanged && Schema::hasTable('notifications') && $loan->consumer?->user) {
                $loan->refresh()->load('consumer.user');
                $this->notifyConsumerOfInstallationSchedule($loan, $isNewSchedule);
            }
        });

        return redirect()
            ->route($this->workflowReturnRoute($request))
            ->with('success', 'Installation schedule saved successfully.');
    }

    public function updateInstallationStatus(Request $request, Loan $loan, LoanInstallationCompletionService $completion): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $request->validate([
            'installation_status' => ['required', 'in:Completed'],
            'physical_installation_confirmed' => ['accepted'],
        ]);

        $completion->complete($loan, $request->user());

        return back()->with('success', 'Installation marked as completed.');
    }

    public function storeRepayment(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if (! Schema::hasTable('loan_repayments')) {
            return redirect()
                ->route('loans.index')
                ->withErrors([
                    'repayments' => 'The loan repayments table is not ready yet. Run php artisan migrate first.',
                ]);
        }

        $loan->loadSum('repayments', 'amount');

        if ($loan->status !== 'Approved') {
            return redirect()
                ->route('loans.index')
                ->withErrors([
                    'loan' => 'Only approved loans can accept repayment postings.',
                ]);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ((float) $validated['amount'] > $loan->balance_remaining) {
            return redirect()
                ->route('loans.index')
                ->withErrors([
                    'repayment' => 'Repayment amount cannot exceed the remaining loan balance of P '.number_format($loan->balance_remaining, 2).'.',
                ]);
        }

        DB::transaction(function () use ($request, $loan, $validated) {
            Consumer::query()->whereKey($loan->consumer_id)->lockForUpdate()->firstOrFail();
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if ($locked->installments()->exists() || (is_array($locked->installment_schedule_snapshot) && $locked->start_billing_period)) {
                app(LoanRepaymentAllocationService::class)->record($locked, (float) $validated['amount'], $validated['payment_date'], $request->user(), notes: $validated['notes'] ?? null);
            } else {
                $locked->loadSum('repayments', 'amount');
                if ($locked->status !== 'Approved' || (float) $validated['amount'] > $locked->balance_remaining) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['repayment' => 'Repayment exceeds the current remaining loan balance.']);
                }
                LoanRepayment::create(['loan_id' => $locked->id, 'recorded_by_user_id' => $request->user()->id,
                    'amount' => $validated['amount'], 'payment_date' => $validated['payment_date'], 'notes' => $validated['notes'] ?? null]);
                $locked->refresh()->loadSum('repayments', 'amount');
                $locked->syncRepaymentStatus();
            }
        });

        $loan->refresh()->loadSum('repayments', 'amount');

        if ($loan->consumer) {
            $loan->consumer->syncAccountStanding();
            app(\App\Services\DisconnectionNoticeService::class)->grantEligibilityIfFullySettled($loan->consumer, (float) $validated['amount']);
        }

        if (Schema::hasTable('notifications') && $loan->consumer?->user) {
            $this->notifyConsumerOfLoanUpdate(
                $loan->load('consumer.user'),
                $loan->status === 'Completed' ? 'Loan fully paid' : 'Loan repayment posted',
                $loan->status === 'Completed'
                    ? sprintf(
                        'A repayment was posted and your loan is now fully paid. Total amount: P %s.',
                        number_format((float) $loan->amount, 2),
                    )
                    : sprintf(
                        'A repayment of P %s was posted. Remaining balance: P %s.',
                        number_format((float) $validated['amount'], 2),
                        number_format((float) $loan->balance_remaining, 2),
                    ),
            );
        }

        return redirect()
            ->route('loans.index')
            ->with('success', $loan->status === 'Completed'
                ? 'Repayment posted and loan marked as completed.'
                : 'Repayment posted successfully. Remaining balance: P '.number_format($loan->balance_remaining, 2).'.');
    }


    private function buildStats($user, DssEligibilityService $dss): array
    {
        $base = Loan::query()
            ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)));
        $consumers = $user->isAdmin()
            ? Consumer::query()->get()
            : Consumer::query()->where('user_id', $user->id)->get();
        $eligibilities = $consumers->map(fn (Consumer $consumer) => $dss->loanEligibility($consumer));

        return [
            'approved' => (clone $base)->whereIn('status', ['Ready for Scheduling', 'Approved'])->count(),
            'pending' => (clone $base)->pendingReview()->count(),
            'high_risk' => (clone $base)->where('risk_level', 'High Risk')->count(),
            'due_this_week' => (clone $base)
                ->where('status', 'Approved')
                ->whereBetween('next_due_date', [today(), today()->copy()->addDays(7)])
                ->count(),
            'eligible_consumers' => $eligibilities->where('eligible', true)->count(),
            'not_eligible_consumers' => $eligibilities->where('eligible', false)->count(),
            'no_record_consumers' => $eligibilities
                ->filter(fn (array $eligibility) => collect($eligibility['metrics'])->only(['billing_records', 'consumption_records', 'verified_payments'])->sum() === 0)
                ->count(),
        ];
    }

    private function recommendationFeed($user): Collection
    {
        return Loan::query()
            ->with('consumer')
            ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
            ->orderByRaw("case when risk_level = 'Low Risk' then 0 when risk_level = 'Medium Risk' then 1 else 2 end")
            ->orderByDesc('applied_at')
            ->limit(4)
            ->get();
    }

    private function repaymentSchedule($user): Collection
    {
        return Loan::query()
            ->with('consumer')
            ->withSum('repayments', 'amount')
            ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
            ->where('status', 'Approved')
            ->whereNotNull('next_due_date')
            ->orderBy('next_due_date')
            ->limit(6)
            ->get();
    }

    private function loanBillingReadiness(Loan $loan, bool $isAdmin, bool $installmentsTableExists): array
    {
        $recommendation = $loan->latestDssEvaluation?->recommendation;
        $schedule = $loan->installment_schedule_snapshot;
        $scheduleIsValid = is_array($schedule)
            && count($schedule) === (int) $loan->term_months
            && collect(array_values($schedule))->every(fn ($row, $index): bool => is_array($row)
                && (! isset($row['month']) || (int) $row['month'] === $index + 1)
                && is_numeric($row['amount'] ?? null)
                && (float) $row['amount'] > 0);
        if ($scheduleIsValid) {
            $scheduleTotal = round((float) collect($schedule)->sum(fn (array $row): float => (float) $row['amount']), 2);
            $scheduleIsValid = abs($scheduleTotal - round((float) $loan->amount, 2)) < 0.01;
        }

        $reasons = [];
        if ($recommendation === 'Rejected') {
            $reasons[] = 'DSS decision is Rejected; installment bills cannot be generated.';
        }
        if ($loan->status !== 'Approved') {
            $reasons[] = 'Loan status is '.$loan->status.'; final Approved status is required for billing.';
        }
        if ((float) $loan->amount <= 0) {
            $reasons[] = 'The saved loan amount is missing or zero.';
        }
        if (! in_array((int) $loan->term_months, [3, 6, 12], true)) {
            $reasons[] = 'The saved repayment term is invalid.';
        }
        if (! $scheduleIsValid) {
            $reasons[] = 'A complete saved repayment schedule is missing or has invalid installment amounts.';
        }
        if (! $loan->start_billing_period) {
            $reasons[] = 'The billing start period has not been activated.';
        }
        $hasSavedInstallmentDueDate = $installmentsTableExists && $loan->installments()->whereNotNull('due_date')->exists();
        $hasExistingBillDueDate = $loan->bills()->where('bill_type', 'Loan')->whereNotNull('due_date')->exists();
        if (! $loan->next_due_date && ! $hasSavedInstallmentDueDate && ! $hasExistingBillDueDate) {
            $reasons[] = 'The first installment due date is missing.';
        }
        $reason = $reasons ? implode(' ', $reasons) : null;
        $installationContext = $loan->installation_status
            ? 'Installation status is '.$loan->installation_status.'; scheduled installation records are not installment bills.'
            : null;

        if ($reason !== null) {
            if ($installationContext) {
                $reason .= ' '.$installationContext;
            }
            $actionUrl = ! $isAdmin
                ? route('billing.loan')
                : ($loan->status !== 'Approved' || $recommendation === 'Rejected'
                    ? route('dss.loans')
                    : route('billing.index'));

            return [
                'ready' => false,
                'reason' => $reason,
                'installation_status' => $loan->installation_status ?: 'Not Scheduled',
                'action_url' => $actionUrl,
                'action_label' => ! $isAdmin
                    ? 'View Loan Billing'
                    : ($loan->status !== 'Approved' || $recommendation === 'Rejected'
                        ? 'View DSS decision'
                        : 'Open Billing Center'),
            ];
        }

        return [
            'ready' => true,
            'reason' => $loan->installation_status === 'Scheduled'
                ? 'Billing is activated from the saved repayment schedule. '.$installationContext
                : 'Billing is activated from the saved repayment schedule.',
            'installation_status' => $loan->installation_status ?: 'Not Scheduled',
            'action_url' => route('billing.loan'),
            'action_label' => 'View Loan Billing',
        ];
    }

    private function scheduledInstallations($user, bool $repaymentsTableExists): Collection
    {
        return Loan::query()
            ->with(['consumer', 'latestDssEvaluation'])
            ->when($repaymentsTableExists, fn ($query) => $query->withSum('repayments', 'amount'))
            ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
            ->where(function ($query): void {
                $query->where('installation_status', 'Scheduled')
                    ->orWhere(fn ($approvedQuery) => $approvedQuery
                        ->where('status', 'Approved')
                        ->whereDoesntHave('bills', fn ($billQuery) => $billQuery->where('bill_type', 'Loan')));
            })
            ->orderBy('installation_date')
            ->orderBy('installation_time')
            ->limit(10)
            ->get();
    }

    private function loanInstallments($user): Collection
    {
        return LoanInstallment::query()
            ->with('loan.consumer')
            ->when($user->isConsumer(), fn ($query) => $query->whereHas('loan.consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
            ->orderBy('billing_period')
            ->orderBy('installment_number')
            ->limit(24)
            ->get();
    }


    private function recentRepayments($user): Collection
    {
        return LoanRepayment::query()
            ->with(['loan.consumer', 'recorder'])
            ->when($user->isConsumer(), fn ($query) => $query->whereHas('loan.consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
            ->latest('payment_date')
            ->latest('id')
            ->limit(6)
            ->get();
    }

    private function scoreConsumer(Consumer $consumer, ?array $eligibility = null): array
    {
        $unpaidBills = $consumer->bills()->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])->count();
        $overdueBills = $consumer->bills()->where('notice_level', \App\Services\ConsumerBalanceService::DISCONNECTION_NOTICE)->count();
        $verifiedPayments = $consumer->payments()->where('status', 'Verified')->count();

        if ($overdueBills >= 2 || $consumer->status === 'Disconnected') {
            return ['High Risk', 'Repeated overdue balances or disconnection status increase the likelihood of repayment issues.'];
        }

        if ($unpaidBills > 0) {
            return ['Medium Risk', 'There are active unpaid billing records that should be monitored alongside repayment.'];
        }

        if ($verifiedPayments >= 1) {
            $billingRecords = $eligibility['metrics']['billing_records'] ?? $consumer->bills()->count();
            $consumptionRecords = $eligibility['metrics']['consumption_records'] ?? $consumer->meterReadings()->count();

            return ['Low Risk', "DSS eligible: active account with {$billingRecords} billing records, {$consumptionRecords} consumption records, verified payment history, and no overdue bills."];
        }

        return ['Medium Risk', 'Limited verified payment history means the application still needs manual review.'];
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: collect(),
            total: 0,
            perPage: 10,
            currentPage: LengthAwarePaginator::resolveCurrentPage(),
            options: [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    private function loanEligibleForInstallation(Loan $loan): bool
    {
        return $loan->exists
            && $loan->status === 'Ready for Scheduling'
            && $loan->latestDssEvaluation?->recommendation === 'Approved'
            && in_array($loan->review_status, ['Reviewed', 'Installation Scheduled'], true);
    }

    private function workflowReturnRoute(Request $request): string
    {
        return $request->boolean('return_to_dss') ? 'dss.loans' : 'loans.index';
    }

    private function loanHasConfiguredInstallationCost(Loan $loan): bool
    {
        return round((float) $loan->installation_cost_total, 2) > 0
            && $loan->material_meter_cost !== null
            && $loan->material_hose_cost !== null
            && $loan->material_other_cost !== null;
    }

    private function notifyAdminsOfNewLoanApplication(Loan $loan): void
    {
        User::query()
            ->where('role', 'admin')
            ->get()
            ->each(fn (User $admin) => $admin->notify(new LoanStatusNotification(
                loan: $loan,
                title: 'New loan application',
                message: "{$loan->consumer->full_name} submitted a loan application.",
            )));
    }

    private function notifyConsumerOfLoanUpdate(Loan $loan, string $title, string $message): void
    {
        $loan->consumer?->user?->notify(new LoanStatusNotification(
            loan: $loan,
            title: $title,
            message: $message,
        ));
    }

    private function notifyConsumerOfInstallationSchedule(Loan $loan, bool $isNewSchedule): void
    {
        $schedule = $loan->installationSchedule();
        $date = $schedule->format('F j, Y');
        $time = $schedule->format('g:i A');

        $message = $isNewSchedule
            ? "Your installation has been scheduled for {$date} at {$time}. Please make sure that you or an authorized representative is available at the installation address."
            : "Your installation schedule has been updated to {$date} at {$time}.";

        $loan->consumer?->user?->notify(new LoanStatusNotification(
            loan: $loan,
            title: 'Installation Schedule',
            message: $message,
            extraData: [
                'installation_date' => $loan->installation_date?->toDateString(),
                'installation_time' => $loan->installation_time ? substr((string) $loan->installation_time, 0, 5) : null,
                'installation_notes' => $loan->installation_notes,
                'review_status' => $loan->review_status,
                'installation_status' => $loan->installation_status,
            ],
        ));

        $recipientEmail = $loan->consumer?->email ?? $loan->consumer?->user?->email;
        if (filled($recipientEmail)) {
            try {
                $links = app(ConsumerEmailUrl::class);
                Mail::send('emails.consumer.loan-installation-scheduled', [
                    'consumer' => $loan->consumer,
                    'loan' => $loan,
                    'installationDate' => $date,
                    'installationTime' => $time,
                    'rescheduleUrl' => app(InstallationRescheduleService::class)->usage($loan->consumer)['available']
                        ? $links->forLoanInstallationReschedule($loan->id)
                        : null,
                    'loanPortalUrl' => $links->tryTo(route('loans.index', absolute: false)) ?? route('loans.index'),
                ], function ($mail) use ($recipientEmail, $isNewSchedule) {
                    $subject = $isNewSchedule
                        ? config('app.name', 'ViaFlow System') . ' Loan Installation Scheduled'
                        : config('app.name', 'ViaFlow System') . ' Updated Loan Installation Schedule';
                    $mail->to($recipientEmail)->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::error('Loan installation schedule email failed to send.', [
                    'loan_id' => $loan->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
