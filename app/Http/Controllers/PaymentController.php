<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Notifications\PaymentStatusNotification;
use App\Rules\FileMimeType;
use App\Services\AdminPaymentMonitoring;
use App\Services\ConsumerBalanceService;
use App\Services\ConsumerBillingScenario;
use App\Services\ConsumerStatusService;
use App\Services\LoanBillingSynchronizer;
use App\Services\LoanRepaymentAllocationService;
use App\Services\PaymentAllocationService;
use App\Services\ReceiptOcrService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request, LoanBillingSynchronizer $loanBillingSynchronizer, AdminPaymentMonitoring $monitoring, ConsumerBillingScenario $scenario): View|RedirectResponse
    {
        $user = $request->user();
        abort_if($user->role === 'meter_reader', 403);

        // The Record Payment form can switch to Loan Payment from either tab,
        // so make eligible saved installments available before building its
        // bill selector. The synchronizer is idempotent and consumer-scoped.
        $consumer = $user->isConsumer() ? $user->consumer : null;
        $consumerId = $user->isConsumer() ? ($consumer?->id ?? 0) : null;
        $hasBillingScenario = ! in_array($request->query('view'), ['loan-payments', 'loan-bills'], true) && $scenario->isAvailableFor($consumer);
        $billingScenario = $hasBillingScenario && $request->query('ledger') !== 'recorded' ? $scenario->snapshot($consumer) : null;
        $loanSync = $billingScenario ? ['review_loan_ids' => []]
            : $loanBillingSynchronizer->synchronize($consumerId, issueFutureFirstBill: ! $user->isConsumer());

        // Keep the consumer's own disconnection notice and payment statuses in
        // step with their real billing and payment records.
        if (! $billingScenario) {
            $user->consumer?->syncAccountStanding();
        }

        $status = trim((string) $request->string('status'));
        $paymentStatuses = ['Pending', 'Verified', 'Rejected'];
        $paymentStatusFilter = match ($status) {
            'Paid', 'Verified' => 'Paid',
            'Pending Payment', 'Pending' => 'Pending Payment',
            'Rejected' => 'Rejected',
            default => '', // Clears obsolete bill-status values such as Unpaid.
        };
        $paymentStatusQuery = match ($paymentStatusFilter) {
            'Paid' => 'Verified',
            'Pending Payment' => 'Pending',
            'Rejected' => 'Rejected',
            default => '',
        };
        $search = trim((string) $request->string('search'));
        $viewType = (string) $request->query('view', 'water-payments');
        if ($viewType === 'loan-bills') {
            $viewType = 'loan-payments';
        }
        if (! $user->isAdmin() && $viewType === 'review') {
            $viewType = 'water-payments';
        }
        if (! in_array($viewType, ['water-payments', 'loan-payments', 'review'], true)) {
            $viewType = 'water-payments';
        }
        $groupedPayments = $user->isAdmin() && $viewType !== 'review';
        $channel = $groupedPayments ? '' : trim((string) $request->string('channel'));
        $year = $groupedPayments ? $request->integer('year') : 0;
        $month = $groupedPayments ? $request->integer('month') : 0;
        $year = $year >= 1000 && $year <= 9999 ? $year : 0;
        $month = $month >= 1 && $month <= 12 ? $month : 0;
        $historyFilters = array_filter([
            'view' => $viewType,
            'search' => $search,
            'status' => $paymentStatusFilter,
            'year' => $year,
            'month' => $month,
        ], fn ($value) => $value !== '' && $value !== 0);
        $paymentTypeFilter = in_array($request->query('payment_type'), ['Water', 'Loan'], true)
            ? (string) $request->query('payment_type')
            : 'All';
        if ($user->isAdmin() && $status === 'Unpaid') {
            $cleanFilters = [
                'view' => $viewType,
                'search' => $search !== '' ? $search : null,
                'channel' => $channel !== '' ? $channel : null,
                'payment_type' => $viewType === 'review' && $paymentTypeFilter !== 'All' ? $paymentTypeFilter : null,
            ];

            return redirect()->route('payments.index', $groupedPayments
                ? $historyFilters
                : array_filter($cleanFilters, fn ($value) => $value !== null && $value !== ''));
        }
        $recordType = match (true) {
            str_starts_with($viewType, 'loan-') => 'Loan',
            $viewType === 'review' => 'Review',
            default => 'Water',
        };
        $showBills = $viewType === 'review';

        $paymentQuery = Payment::query()
            ->when($billingScenario, fn ($query) => $query->onlyBillingFixtures())
            ->with([
                'bill' => fn ($query) => $query
                    ->when($billingScenario, fn ($query) => $query->onlyBillingFixtures())
                    ->with('consumer')
                    ->with('loanInstallment')
                    ->withSum(['payments as verified_payments_sum_amount' => fn ($paymentQuery) => $paymentQuery->where('status', 'Verified')], 'amount'),
                'consumer',
                'verifier',
                'loan',
            ])
            ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
            ->when($viewType === 'review', fn ($query) => $query
                ->whereIn('payment_type', ['Water', 'Loan'])
                ->where('status', 'Pending')
                ->when($paymentTypeFilter !== 'All', fn ($reviewQuery) => $reviewQuery->where('payment_type', $paymentTypeFilter)))
            ->when($viewType !== 'review', fn ($query) => $query->where('payment_type', $recordType))
            ->when($user->isAdmin() && $viewType !== 'review' && $paymentStatusQuery !== '', fn ($query) => $query->where('status', $paymentStatusQuery))
            ->when($user->isConsumer() && $viewType !== 'review' && in_array($status, $paymentStatuses, true), fn ($query) => $query->where('status', $status))
            ->when($channel !== '', fn ($query) => $query->where('payment_channel', $channel))
            ->when($groupedPayments, function ($query) use ($monitoring, $year, $month) {
                $query->select('payments.*')->selectRaw(AdminPaymentMonitoring::TRANSACTION_DATE.' as monitoring_date')
                    ->withCasts(['monitoring_date' => 'datetime']);
                $monitoring->filterByDate($query, AdminPaymentMonitoring::TRANSACTION_DATE, $year, $month);
            })
            ->when($groupedPayments && $recordType === 'Loan', fn ($query) => $query->with('loanRepayment.allocations.installment'))
            ->when($user->isAdmin() && $search !== '', fn ($query) => $monitoring->filterSearch($query, $search, 'consumer', ['reference_number', 'receipt_number', 'gateway_reference']))
            ->when(! $user->isAdmin() && $search !== '', function ($query) use ($search, $billingScenario) {
                $query->where(function ($paymentQuery) use ($search, $billingScenario) {
                    $paymentQuery
                        ->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('receipt_number', 'like', "%{$search}%")
                        ->orWhere('gateway_reference', 'like', "%{$search}%")
                        ->orWhereHas('bill', fn ($billQuery) => $billQuery
                            ->when($billingScenario, fn ($query) => $query->onlyBillingFixtures())->where('prn', 'like', "%{$search}%"))
                        ->orWhereHas('consumer', function ($consumerQuery) use ($search) {
                            $consumerQuery
                                ->where('full_name', 'like', "%{$search}%")
                                ->orWhere('account_number', 'like', "%{$search}%");
                        });
                });
            })
            // The review queue follows submission time. The consumer history
            // follows payment date. Grouped histories sort the combined rows below.
            ->when($user->isConsumer(), fn ($query) => $query
                ->orderByDesc('payment_date')
                ->orderByDesc('id'))
            ->when($viewType === 'review', fn ($query) => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id'));
        $allAdminPayments = $user->isAdmin() ? (clone $paymentQuery)->get() : collect();
        $payments = $user->isConsumer() ? $paymentQuery->paginate(10)->withQueryString() : null;

        $balances = app(ConsumerBalanceService::class);
        $availableBills = $billingScenario['bills'] ?? ($user->isConsumer() && $user->consumer
            ? Bill::query()->with(['consumer', 'loanInstallment.loan'])
                ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount')
                ->where('consumer_id', $user->consumer->id)->where('bill_type', $recordType)->orderByDesc('billing_period')->get()
            : Bill::query()
                ->with('consumer')
                ->with('loanInstallment.loan')
                ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount')
                ->where('bill_type', $recordType)
                ->orderByDesc('billing_period')
                ->get());
        $paymentFormBills = $billingScenario ? collect() : Bill::query()
            ->forCollection()
            ->with(['consumer', 'loanInstallment.loan'])
            ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount')
            ->whereIn('bill_type', ['Water', 'Loan'])
            ->whereNull('carried_forward_to_bill_id')
            ->when($user->isConsumer(), fn ($query) => $query->where('consumer_id', $user->consumer?->id ?? 0))
            ->where(function ($query) {
                $query->where(fn ($waterQuery) => $waterQuery->where('bill_type', 'Water')->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance']))
                    ->orWhere(fn ($loanQuery) => $loanQuery->where('bill_type', 'Loan')->whereHas('loanInstallment.loan', fn ($loan) => $loan->whereIn('status', ['Approved', 'Active', 'Ongoing'])));
            })
            ->orderBy('billing_period')
            ->orderBy('id')
            ->get()
            ->filter(function (Bill $bill): bool {
                if ($bill->bill_type === 'Loan') {
                    return $bill->loanInstallment?->loan !== null
                        && app(LoanRepaymentAllocationService::class)->remainingBalance($bill->loanInstallment->loan) > 0;
                }

                return ! $bill->balanceIsCarriedForward() && (float) $bill->balance_due > 0;
            })
            ->values();
        $disconnectionPaymentSummaries = $billingScenario ? [] : $availableBills
            ->pluck('consumer_id')
            ->unique()
            ->mapWithKeys(fn (int $consumerId) => [
                $consumerId => $recordType === 'Water'
                    ? $balances->paymentRuleSummaryFor($consumerId)
                    : ['active' => false, 'notice' => 'None', 'total_outstanding' => 0, 'required_payment_amount' => null, 'message' => 'Loan installments may be paid separately.'],
            ])
            ->all();
        $formDisconnectionSummaries = $paymentFormBills->pluck('consumer_id')->unique()
            ->mapWithKeys(fn (int $consumerId) => [$consumerId => $balances->paymentRuleSummaryFor($consumerId)])->all();
        $allocation = app(LoanRepaymentAllocationService::class);
        $paymentFormLoans = $billingScenario ? collect() : Loan::query()
            ->when($user->isConsumer(), fn ($query) => $query->where('consumer_id', $consumerId))
            ->whereIn('status', Loan::PAYMENT_STATUSES)->where('amount', '>', 0)
            ->with(['installments' => fn ($query) => $query->orderBy('installment_number')])->orderBy('id')->get()
            ->filter(function (Loan $loan) use ($allocation, $loanSync): bool {
                $loan->payment_balance = $allocation->remainingBalance($loan);
                if ($loan->payment_balance <= 0) {
                    return false;
                }

                $loan->payment_limit = in_array($loan->id, $loanSync['review_loan_ids'], true) ? 0.0 : $allocation->paymentLimit($loan);
                $loan->payment_selectable = $loan->payment_limit >= $loan->payment_balance;

                return true;
            })->values();
        $paymentConsumers = $user->isConsumer() ? collect() : $this->buildPaymentConsumerDirectory(
            $paymentFormBills->where('bill_type', 'Water'), $formDisconnectionSummaries, $paymentFormLoans,
        );

        $paymentRecords = null;
        $paymentAccounts = null;
        $transactionCount = 0;
        $manualLoanRepayments = $recordType === 'Loan'
            ? LoanRepayment::query()->with(['loan.consumer', 'bill.loanInstallment'])->whereNull('payment_id')
                ->when($user->isAdmin() && in_array($paymentStatusFilter, ['Pending Payment', 'Rejected'], true), fn ($query) => $query->whereRaw('1 = 0'))
                ->when($user->isConsumer() && in_array($status, ['Pending', 'Rejected'], true), fn ($query) => $query->whereRaw('1 = 0'))
                ->when($channel !== '' && $channel !== 'Counter', fn ($query) => $query->whereRaw('1 = 0'))
                ->when($groupedPayments, function ($query) use ($monitoring, $year, $month) {
                    $query->with('allocations.installment');
                    $monitoring->filterByDate($query, 'loan_repayments.payment_date', $year, $month);
                })
                ->when($user->isConsumer(), fn ($query) => $query->whereHas('loan.consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
                ->when($groupedPayments && $search !== '', fn ($query) => $monitoring->filterSearch($query, $search, 'loan.consumer'))
                ->when(! $groupedPayments && $search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->whereHas('loan.consumer', fn ($consumerQuery) => $consumerQuery->where('full_name', 'like', "%{$search}%")->orWhere('account_number', 'like', "%{$search}%"))
                        ->orWhereHas('bill', fn ($billQuery) => $billQuery->where('prn', 'like', "%{$search}%"));
                }))
                ->orderByDesc('payment_date')->orderByDesc('id')->get()
            : collect();
        if ($user->isAdmin()) {
            $openBillRows = ($showBills ? $availableBills : collect())
                ->filter(fn (Bill $bill) => ($showBills || $channel === '')
                    && ($search === '' || collect([
                        $bill->prn,
                        $bill->consumer?->full_name,
                        $bill->consumer?->account_number,
                        $bill->consumer?->meter_number,
                    ])->contains(fn ($value) => stripos((string) $value, $search) !== false)))
                ->map(fn (Bill $bill) => [
                    'kind' => 'bill',
                    'record' => $bill,
                    'sort_at' => optional($bill->due_date)->timestamp ?? optional($bill->billing_period)->timestamp ?? 0,
                ]);
            $paymentRows = ((! $showBills || $recordType === 'Review') ? $allAdminPayments : collect())
                ->map(function (Payment $payment) use ($groupedPayments) {
                    $date = $groupedPayments ? $payment->monitoring_date : $payment->created_at;

                    return ['kind' => 'payment', 'record' => $payment, 'date' => $date, 'sort_at' => $date?->timestamp ?? 0];
                });
            $manualRepaymentRows = $viewType === 'loan-payments'
                ? $manualLoanRepayments->map(fn (LoanRepayment $repayment) => [
                    'kind' => 'loan-repayment',
                    'record' => $repayment,
                    'date' => $repayment->payment_date,
                    'sort_at' => optional($repayment->payment_date)->timestamp ?? 0,
                ])
                : collect();
            $rows = $openBillRows->concat($paymentRows)->concat($manualRepaymentRows);

            $rows = $rows->sort(function (array $left, array $right) {
                return ($right['sort_at'] <=> $left['sort_at'])
                    ?: ($right['record']->id <=> $left['record']->id)
                    ?: strcmp($left['kind'], $right['kind']);
            })->values();

            $transactionCount = $rows->count();
            if ($groupedPayments) {
                $paymentAccounts = $monitoring->accounts($rows, $request, $historyFilters);
            } else {
                $page = LengthAwarePaginator::resolveCurrentPage();
                $perPage = 10;
                $paymentRecords = new LengthAwarePaginator(
                    $rows->forPage($page, $perPage)->values(),
                    $transactionCount,
                    $perPage,
                    $page,
                    ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()],
                );
            }
        }

        $statsQuery = fn () => Payment::query()
            ->when($billingScenario, fn ($query) => $query->onlyBillingFixtures())
            ->when($viewType === 'review', fn ($query) => $query
                ->whereIn('payment_type', ['Water', 'Loan'])
                ->when($paymentTypeFilter !== 'All', fn ($reviewQuery) => $reviewQuery->where('payment_type', $paymentTypeFilter)))
            ->when($viewType !== 'review', fn ($query) => $query->where('payment_type', $recordType))
            ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)));

        return view($this->roleViewPath($request, 'payments.index'), [
            'payments' => $payments,
            'hasBillingScenario' => $hasBillingScenario,
            'billingScenario' => $billingScenario,
            'paymentRecords' => $paymentRecords,
            'paymentAccounts' => $paymentAccounts,
            'transactionCount' => $transactionCount,
            'paymentYears' => $groupedPayments ? $monitoring->years($recordType) : collect(),
            'availableBills' => $availableBills,
            'paymentFormBills' => $paymentFormBills,
            'paymentFormLoans' => $paymentFormLoans,
            'viewType' => $viewType,
            'recordType' => $recordType,
            'paymentTypeFilter' => $paymentTypeFilter,
            'showBills' => $showBills,
            'loanInstallments' => $recordType === 'Loan'
                ? \App\Models\LoanInstallment::query()->with(['loan.consumer', 'bill'])->when($user->isConsumer(), fn ($query) => $query->whereHas('loan.consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))->get()
                : collect(),
            'manualLoanRepayments' => $manualLoanRepayments,
            'disconnectionPaymentSummaries' => $disconnectionPaymentSummaries,
            'paymentFormDisconnectionSummaries' => $formDisconnectionSummaries,
            'paymentConsumers' => $paymentConsumers,
            'statuses' => $user->isConsumer() ? $paymentStatuses : ['Paid', 'Pending Payment', 'Rejected'],
            'channels' => ['Counter', 'Online'],
            'paymentMethods' => ['Cash', 'Bank Transfer', 'GCash', 'Maya', 'Other E-Wallet', 'Other'],
            'filters' => [
                'status' => $user->isConsumer() ? $status : $paymentStatusFilter,
                'search' => $search,
                'year' => $year,
                'month' => $month,
                'payment_type' => $paymentTypeFilter,
            ] + ($groupedPayments ? [] : ['channel' => $channel]),
            'stats' => [
                'pending' => $statsQuery()->where('status', 'Pending')->count(),
                'verified_today' => $statsQuery()
                    ->where('status', 'Verified')
                    ->whereDate('verified_at', today())
                    ->count(),
                'online_submissions' => $statsQuery()
                    ->where('payment_channel', 'Online')
                    ->count(),
                'verified_amount_today' => (float) $statsQuery()
                    ->where('status', 'Verified')
                    ->whereDate('verified_at', today())
                    ->sum('amount'),
            ],
            'canVerifyPayments' => $user->isAdmin(),
        ]);
    }

    public function store(Request $request, ReceiptOcrService $receiptOcrService, PaymentAllocationService $payments): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->role === 'meter_reader', 403);
        $recordedLedger = $user->isConsumer() && $request->query('ledger') === 'recorded' ? ['ledger' => 'recorded'] : [];

        $tabPaymentType = $user->isConsumer() ? match ($request->query('view')) {
            'water-payments' => 'Water',
            'loan-payments' => 'Loan',
            default => null,
        } : null;

        $requiresReceiptOcr = $user->isConsumer()
            && $request->hasFile('proof')
            && $request->input('payment_channel') === 'Online';
        $requiresConsumerReceiptOcr = $user->isConsumer()
            && $request->input('payment_channel') === 'Online'
            && $request->hasFile('proof');

        $validated = $request->validate([
            'bill_id' => ['nullable', 'required_without:loan_id', 'exists:bills,id'],
            'loan_id' => ['nullable', 'integer', 'exists:loans,id'],
            'payment_type' => ['nullable', Rule::in(['Water', 'Loan'])],
            'submission_key' => ['nullable', 'uuid', Rule::unique('payments', 'submission_key')],
            'consumer_id' => ['nullable', 'integer', 'exists:consumers,id'],
            'amount' => [$requiresReceiptOcr ? 'nullable' : 'required', 'numeric', 'min:0.01'],
            'payment_date' => [$requiresReceiptOcr ? 'nullable' : 'required', 'date'],
            'payment_channel' => ['required', 'in:Counter,Online'],
            'payment_method' => ['required', 'in:Cash,Bank Transfer,GCash,Maya,Other E-Wallet,Other'],
            'gateway_reference' => ['nullable', 'string', 'max:100', Rule::unique('payments', 'gateway_reference')],
            'proof' => $user->isConsumer()
                ? ['nullable', 'file', 'extensions:jpg,jpeg,png,pdf', 'max:2048', new FileMimeType('jpg,jpeg,png,pdf')]
                : ['prohibited'],
            'sender_name' => ['nullable', 'string', 'max:100'],
            'sender_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'gateway_reference.unique' => 'This reference or transaction number has already been submitted.',
        ]);

        $directLoan = ! empty($validated['loan_id']) ? Loan::query()->with('consumer')->findOrFail($validated['loan_id']) : null;
        $bill = ! empty($validated['bill_id']) ? Bill::query()->with('consumer')->findOrFail($validated['bill_id']) : null;
        $validated['payment_type'] = $tabPaymentType ?? $validated['payment_type'] ?? $bill?->bill_type;
        if ($directLoan && ($bill || $validated['payment_type'] !== 'Loan')) {
            return back()->withInput()->withErrors(['loan_id' => 'Select a loan for a loan payment.']);
        }
        if ($directLoan && $user->isConsumer() && $directLoan->consumer?->user_id !== $user->id) {
            abort(403);
        }
        $consumerId = $directLoan?->consumer_id ?? $bill?->consumer_id;

        if ($bill && $bill->bill_type !== $validated['payment_type']) {
            return back()->withInput()->withErrors(['bill_id' => 'Payment type must match the selected bill. Ambiguous historical bills require administrator review.']);
        }

        if (! empty($validated['consumer_id']) && (int) $validated['consumer_id'] !== (int) $consumerId) {
            return back()
                ->withInput()
                ->withErrors([
                    'consumer_id' => $directLoan
                        ? 'The selected loan does not belong to the selected consumer account. Please select the account again.'
                        : 'The selected bill does not belong to the selected consumer account. Please select the account again.',
                ]);
        }

        if ($bill && $user->isConsumer() && $bill->consumer?->user_id !== $user->id) {
            abort(403);
        }
        if ($bill?->is_demo) {
            return back()->withErrors(['bill_id' => 'Demo bills are excluded from payment collection.']);
        }
        if ($bill?->superseded_by_bill_id) {
            return back()->withErrors(['bill_id' => 'This billing statement was superseded. Select a current bill.']);
        }


        if ($user->isConsumer() && $validated['payment_type'] === 'Water') {
            $payableBills = $payments->payableBills($bill->consumer_id);
            $totalBalance = $payableBills->sum(fn (Bill $payableBill) => $payableBill->balance_due);

            if ($totalBalance <= 0) {
                return redirect()
                    ->route('payments.index', $recordedLedger)
                    ->withErrors([
                        'bill_id' => 'No balance available for payment.',
                    ]);
            }

            if (! $payableBills->contains('id', $bill->id)) {
                return redirect()
                    ->route('payments.index', $recordedLedger)
                    ->withErrors([
                        'bill_id' => 'This bill balance was already carried into a newer bill. Please pay the latest bill instead.',
                    ]);
            }
        }

        if ($bill && $bill->bill_type !== 'Loan' && ($bill->status === 'Paid' || $bill->balance_due <= 0)) {
            return redirect()
                ->route('payments.index', $recordedLedger)
                ->withErrors([
                    'bill_id' => 'This bill is already fully paid.',
                ]);
        }

        if ($user->isConsumer() && $validated['payment_channel'] !== 'Online') {
            return back()
                ->withInput()
                ->withErrors([
                    'payment_channel' => 'Consumers can only submit online payments for verification.',
                ]);
        }

        if ($validated['payment_channel'] === 'Online' && $validated['payment_method'] === 'Cash') {
            return back()
                ->withInput()
                ->withErrors([
                    'payment_method' => 'Cash is only valid for in-person counter payments.',
                ]);
        }

        if ($requiresConsumerReceiptOcr && ! $request->hasFile('proof')) {
            return back()
                ->withInput()
                ->withErrors([
                    'proof' => 'Please upload a payment receipt or confirmation for automatic scanning.',
                ]);
        }

        if ($requiresReceiptOcr) {
            if (! $request->hasFile('proof')) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'proof' => 'Please upload a payment receipt or confirmation for automatic scanning.',
                    ]);
            }

            try {
                $ocrResult = $receiptOcrService->extract($request->file('proof'));
            } catch (\Throwable $exception) {
                \Log::warning('Receipt OCR failed before payment submission', [
                    'message' => $exception->getMessage(),
                ]);
                $ocrResult = [
                    'ok' => false,
                    'fields' => [],
                    'missing_fields' => ['Reference Number', 'Amount Paid', 'Payment Date'],
                ];
            }

            $ocrFields = $ocrResult['fields'] ?? [];
            $detectedReference = $this->validReceiptReference($ocrFields['reference_number'] ?? null) ?? '';
            $detectedAmount = $this->validReceiptAmount($ocrFields['amount'] ?? null);
            $detectedDate = $this->validReceiptDate($ocrFields['payment_date'] ?? null);

            $manualReference = trim((string) ($validated['gateway_reference'] ?? ''));
            $manualAmount = $validated['amount'] ?? null;
            $manualDate = $validated['payment_date'] ?? null;

            $referenceNumber = $detectedReference !== '' ? $detectedReference : $manualReference;
            $amount = $detectedAmount ?? $manualAmount;
            $paymentDate = $detectedDate ?? $manualDate;

            $missingFields = [];

            if ($referenceNumber === '') {
                $missingFields['gateway_reference'] = 'Reference Number';
            }

            if ($amount === null || $amount === '') {
                $missingFields['amount'] = 'Amount Paid';
            }

            if (blank($paymentDate)) {
                $missingFields['payment_date'] = 'Payment Date';
            }

            if ($missingFields !== []) {
                $fieldErrors = [];

                foreach ($missingFields as $field => $label) {
                    $fieldErrors[$field] = $label.' could not be detected automatically. Enter it manually from the receipt.';
                }

                return back()
                    ->withInput()
                    ->withErrors($fieldErrors);
            }

            try {
                $paymentDate = now()->parse((string) $paymentDate)->toDateString();
            } catch (\Throwable) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'payment_date' => 'Enter a valid payment date from the receipt.',
                    ]);
            }

            if (! is_numeric($amount) || (float) $amount <= 0) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'amount' => 'Enter a valid amount paid from the receipt.',
                    ]);
            }

            if (! ($ocrResult['ok'] ?? false)) {
                \Log::info('Receipt OCR was unavailable; supplied payment details were kept for manual verification.', [
                    'payment_channel' => $validated['payment_channel'],
                    'has_reference' => $referenceNumber !== '',
                ]);
            }

            $validated['gateway_reference'] = $referenceNumber;
            $validated['amount'] = number_format((float) $amount, 2, '.', '');
            $validated['payment_date'] = $paymentDate;
        }

        if ($validated['payment_channel'] === 'Online' && blank($validated['gateway_reference'] ?? null)) {
            return back()
                ->withInput()
                ->withErrors([
                    'gateway_reference' => 'A transaction reference is required for online payments.',
                ]);
        }

        if (! empty($validated['gateway_reference'])) {
            $duplicateReferenceExists = Payment::query()
                ->where('gateway_reference', $validated['gateway_reference'])
                ->exists();

            if ($duplicateReferenceExists) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'gateway_reference' => 'This reference or transaction number has already been submitted.',
                    ]);
            }
        }

        $loanInstallment = $validated['payment_type'] === 'Loan' && $bill
            ? \App\Models\LoanInstallment::query()->with('loan')->where('bill_id', $bill->id)->first()
            : null;
        if ($validated['payment_type'] === 'Loan' && abs((float) $validated['amount'] - round((float) $validated['amount'], 2)) > 0.000001) {
            return back()->withInput()->withErrors(['amount' => 'Enter a loan payment with no more than two decimal places.']);
        }
        if ($validated['payment_type'] === 'Loan' && ! $directLoan && (! $loanInstallment || (int) $loanInstallment->loan?->consumer_id !== (int) $consumerId)) {
            return back()->withInput()->withErrors(['bill_id' => 'This loan bill has no valid installment schedule for this consumer and cannot accept payment.']);
        }
        $selectedLoan = $directLoan ?? $loanInstallment?->loan;
        if ($selectedLoan) {
            if (! in_array($selectedLoan->status, Loan::PAYMENT_STATUSES, true)) {
                return back()->withInput()->withErrors(['loan_id' => 'Only approved loans can accept payments.']);
            }
            $sync = app(LoanBillingSynchronizer::class)->synchronize($consumerId, issueFutureFirstBill: false);
            if (in_array($selectedLoan->id, $sync['review_loan_ids'], true)) {
                return back()->withInput()->withErrors(['bill_id' => 'This loan schedule requires administrator review before a payment can be submitted.']);
            }
            $selectedLoan->refresh();
        }
        $totalOutstanding = $validated['payment_type'] === 'Water'
            ? $payments->totalOutstanding($consumerId)
            : app(LoanRepaymentAllocationService::class)->remainingBalance($selectedLoan);
        if ($validated['payment_type'] === 'Water') {
            $payments->validateAmount($bill->consumer_id, (float) $validated['amount']);
        }

        // The account's total outstanding balance is always the absolute
        // ceiling. Every verified payment is applied oldest-bill-first.
        if (round((float) $validated['amount'], 2) > round($totalOutstanding, 2)) {
            return back()
                ->withInput()
                ->withErrors([
                    'amount' => 'Payment amount cannot exceed the total outstanding balance of P '.number_format($totalOutstanding, 2).'.',
                ]);
        }
        if ($selectedLoan && round((float) $validated['amount'], 2) > app(LoanRepaymentAllocationService::class)->paymentLimit($selectedLoan)) {
            return back()->withInput()->withErrors(['amount' => 'The saved loan schedule needs administrator review before this amount can be paid.']);
        }

        $proofPath = $request->hasFile('proof')
            ? $request->file('proof')->storeAs(
                'payment-proofs',
                Str::random(40).'.'.$request->file('proof')->getClientOriginalExtension(),
                'local'
            )
            : null;

        $status = $user->isAdmin() ? 'Verified' : 'Pending';
        $payment = null;
        $paymentNotes = $this->buildPaymentNotes(
            $validated['notes'] ?? null,
            $validated['sender_name'] ?? null,
            $validated['sender_phone'] ?? null,
        );

        DB::transaction(function () use ($bill, $selectedLoan, $consumerId, $directLoan, $validated, $status, $user, $proofPath, $paymentNotes, $payments, &$payment) {
            Consumer::query()->whereKey($consumerId)->lockForUpdate()->firstOrFail();
            $lockedLoan = $validated['payment_type'] === 'Loan'
                ? Loan::query()->whereKey($selectedLoan->id)->lockForUpdate()->firstOrFail()
                : null;
            $lockedBill = $bill ? Bill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail() : null;
            if ($validated['payment_type'] === 'Water') {
                $payments->validateAmount($bill->consumer_id, (float) $validated['amount']);
                if (round((float) $validated['amount'], 2) > $payments->totalOutstanding($bill->consumer_id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'The outstanding water balance changed. Refresh Payments and enter the current amount.']);
                }
            } else {
                $lockedInstallment = $lockedBill ? \App\Models\LoanInstallment::query()->where('bill_id', $lockedBill->id)->firstOrFail() : null;
                if ((int) $lockedLoan->consumer_id !== (int) $consumerId
                    || ($lockedBill && (int) $lockedInstallment->loan_id !== (int) $lockedLoan->id)
                    || ! in_array($lockedLoan->status, Loan::PAYMENT_STATUSES, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $directLoan ? 'loan_id' : 'bill_id' => 'The selected loan changed. Refresh and try again.',
                    ]);
                }
                if (round((float) $validated['amount'], 2) > app(LoanRepaymentAllocationService::class)->remainingBalance($lockedLoan)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Payment cannot exceed the remaining loan balance.']);
                }
                if (round((float) $validated['amount'], 2) > app(LoanRepaymentAllocationService::class)->paymentLimit($lockedLoan)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'The saved loan schedule needs administrator review before this amount can be paid.']);
                }
            }
            $payment = Payment::create([
                'bill_id' => $bill?->id,
                'loan_id' => $directLoan?->id,
                'consumer_id' => $consumerId,
                'payment_type' => $validated['payment_type'],
                'submission_key' => $validated['submission_key'] ?? null,
                'reference_number' => $payments->generateReferenceNumber(),
                'receipt_number' => $status === 'Verified' ? $this->generateReceiptNumber() : null,
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'payment_channel' => $validated['payment_channel'],
                'gateway_reference' => $validated['gateway_reference'] ?? null,
                'status' => $status,
                'verified_at' => $status === 'Verified' ? now() : null,
                'verified_by' => $status === 'Verified' ? $user->id : null,
                'proof_path' => $proofPath,
                'notes' => $paymentNotes,
            ]);

            if ($status === 'Verified') {
                // Every verified payment is allocated oldest-outstanding-bill
                // first, whether it clears the whole balance or only part of
                // it. Only the unpaid remainder is ever carried forward.
                if ($payment->payment_type === 'Loan') {
                    $payments->allocateVerifiedLoanPayment($payment, $user);
                } else {
                    $payments->allocateVerifiedFullPayment($payment, $user, $payment->receipt_number);
                }

                $payment->refresh();
            }
        });

        $bill?->refresh()->loadSum([
            'payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified'),
        ], 'amount');

        $eligibilityGranted = $status === 'Verified'
            ? app(\App\Services\DisconnectionNoticeService::class)->grantEligibilityIfFullySettled($payment->consumer, (float) $payment->amount)
            : false;

        if (Schema::hasTable('notifications') && $payment) {
            if ($status === 'Verified') {
                $this->notifyPaymentVerified($payment);
            } else {
                $this->notifyAdminsOfPendingPayment($payment);
            }
        }

        if ($status === 'Verified' && $payment) {
            return redirect()
                ->route('payments.receipt', $payment)
                ->with('success', $eligibilityGranted
                    ? 'Payment recorded and verified. The consumer balance is fully settled and the account is ready for reconnection.'
                    : 'Payment recorded, verified, and digital receipt generated.');
        }

        return redirect()
            ->route('payments.index', ($tabPaymentType ? ['view' => $request->query('view')] : []) + $recordedLedger)
            ->with('success', 'Payment has been submitted and is Pending Verification by the administrator.');
    }

    public function verify(Request $request, Payment $payment, PaymentAllocationService $payments): RedirectResponse
    {
        if ($payment->status !== 'Pending') {
            return redirect()
                ->route('payments.index')
                ->withErrors([
                    'status' => 'Only pending payments can be verified.',
                ]);
        }

        if ($payment->payment_type === 'Loan') {
            $loanId = $payment->loan_id ?? $payment->bill?->loanInstallment?->loan_id;
            $sync = app(LoanBillingSynchronizer::class)->synchronize($payment->consumer_id, issueFutureFirstBill: false);
            if ($loanId && in_array($loanId, $sync['review_loan_ids'], true)) {
                return back()->withErrors(['status' => 'This loan schedule requires administrator review before a payment can be verified.']);
            }
        }

        DB::transaction(function () use (&$payment, $payments) {
            Consumer::query()->whereKey($payment->consumer_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'Pending') {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Only pending payments can be verified.']);
            }
            $bill = $payment->bill()->first();
            if ((! $bill && ($payment->payment_type !== 'Loan' || ! $payment->loan_id))
                || ($bill && $payment->payment_type !== $bill->bill_type) || ! in_array($payment->payment_type, ['Water', 'Loan'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['payment_type' => 'Payment and bill categories do not match.']);
            }
            $requiresFullPayment = $payment->payment_type === 'Water' && $payments->requiresFullPayment($payment->consumer_id);
            $loan = $payment->payment_type === 'Loan'
                ? Loan::query()->whereKey($payment->loan_id ?? $bill?->loanInstallment?->loan_id)->where('consumer_id', $payment->consumer_id)->lockForUpdate()->firstOrFail()
                : null;
            if ($loan && LoanRepayment::query()->where('payment_id', $payment->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'This pending loan payment already has a repayment record and requires administrator review.']);
            }
            $totalOutstanding = $payment->payment_type === 'Water'
                ? $payments->totalOutstanding($payment->consumer_id)
                : app(LoanRepaymentAllocationService::class)->remainingBalance($loan);

            if ($payment->payment_type === 'Water') {
                $payments->validateAmount($payment->consumer_id, (float) $payment->amount);
            }

            if ($requiresFullPayment && round((float) $payment->amount, 2) !== round($totalOutstanding, 2)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => $payments->fullPaymentRequiredMessage($totalOutstanding),
                ]);
            }

            $paymentCeiling = $payment->payment_type === 'Loan' || $requiresFullPayment ? $totalOutstanding : $bill->balance_due;
            if (round((float) $payment->amount, 2) > round((float) $paymentCeiling, 2)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed the current remaining balance of P '.number_format($paymentCeiling, 2).'.',
                ]);
            }
            if ($loan && round((float) $payment->amount, 2) > app(LoanRepaymentAllocationService::class)->paymentLimit($loan)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'The saved loan schedule needs administrator review before this amount can be paid.']);
            }

            $payment->update([
                'status' => 'Verified',
                'receipt_number' => $payment->receipt_number ?: $this->generateReceiptNumber(),
                'verified_at' => now(),
                'verified_by' => auth()->id(),
            ]);

            // Always allocate oldest-first so every covered period is settled.
            if ($payment->payment_type === 'Loan') {
                $payments->allocateVerifiedLoanPayment($payment, auth()->user());
            } else {
                $payments->allocateVerifiedFullPayment($payment, auth()->user(), $payment->receipt_number);
            }
        });

        $payment->refresh();

        // Admin verification of a payment that clears the account is what grants
        // reconnection eligibility - paying alone is not enough.
        $eligibilityGranted = app(\App\Services\DisconnectionNoticeService::class)
            ->grantEligibilityIfFullySettled($payment->consumer, (float) $payment->amount);

        if (Schema::hasTable('notifications')) {
            $this->notifyPaymentVerified($payment);
        }

        if ($request->boolean('return_to_review')) {
            return redirect()
                ->route('payments.index', [
                    'view' => 'review',
                    'payment_type' => in_array($request->input('payment_type_filter'), ['Water', 'Loan'], true) ? $request->input('payment_type_filter') : 'All',
                ])
                ->with('success', 'Payment verified successfully. The review queue and counts have been refreshed.');
        }

        return redirect()
            ->route('payments.receipt', $payment)
            ->with('success', $eligibilityGranted
                ? 'Payment verified successfully. Your balance is fully settled and your account is now ready for reconnection.'
                : 'Payment verified successfully and digital receipt generated.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        if ($payment->status !== 'Pending') {
            return redirect()
                ->route('payments.index')
                ->withErrors([
                    'status' => 'Only pending payments can be rejected.',
                ]);
        }

        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        $reason = trim($validated['rejection_reason']);
        if ($reason === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['rejection_reason' => 'A rejection reason is required.']);
        }

        DB::transaction(function () use (&$payment, $reason): void {
            Consumer::query()->whereKey($payment->consumer_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'Pending') {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Only pending payments can be rejected.']);
            }
            $payment->update(['status' => 'Rejected', 'rejection_reason' => $reason]);
        });

        if (Schema::hasTable('notifications')) {
            $this->notifyPaymentRejected($payment->fresh(['bill', 'consumer']));
        }

        if ($request->boolean('return_to_review')) {
            return redirect()
                ->route('payments.index', [
                    'view' => 'review',
                    'payment_type' => in_array($request->input('payment_type_filter'), ['Water', 'Loan'], true) ? $request->input('payment_type_filter') : 'All',
                ])
                ->with('success', 'Payment rejected successfully. The review queue and counts have been refreshed.');
        }

        return redirect()
            ->route('payments.index')
            ->with('success', 'Payment rejected successfully.');
    }

    public function receipt(Request $request, Payment $payment): View
    {
        $payment = $this->receiptPayment($request, $payment);

        return view($this->roleViewPath($request, 'payments.receipt'), $this->receiptData($payment));
    }

    public function receiptPdf(Request $request, Payment $payment): Response
    {
        $payment = $this->receiptPayment($request, $payment);
        $filename = 'viaflow-payment-receipt-'
            .(Str::slug($payment->receipt_number ?: $payment->reference_number) ?: $payment->id)
            .'.pdf';

        return Pdf::loadView('shared.payments.receipt-pdf', $this->receiptData($payment))
            ->setPaper('a5', 'portrait')
            ->download($filename);
    }

    private function receiptPayment(Request $request, Payment $payment): Payment
    {
        abort_if($request->user()->role === 'meter_reader', 403);

        $payment->load([
            'bill' => fn ($query) => $query
                ->with(['consumer', 'loanInstallment'])
                ->withSum(['payments as verified_payments_sum_amount' => fn ($paymentQuery) => $paymentQuery->where('status', 'Verified')], 'amount'),
            'consumer',
            'verifier',
            'loanRepayment.allocations.installment',
        ]);

        if ($payment->status !== 'Verified') {
            abort(404);
        }

        if ($request->user()->isConsumer() && $payment->consumer?->user_id !== $request->user()->id) {
            abort(403);
        }

        return $payment;
    }

    /**
     * @return array{payment: Payment, previousBalance: float, remainingBalance: float}
     */
    private function receiptData(Payment $payment): array
    {
        $remainingBalance = round((float) $payment->remaining_balance_after_payment, 2);
        $previousBalance = $payment->payment_type === 'Loan' && $payment->loanRepayment?->loan_balance_before !== null
            ? (float) $payment->loanRepayment->loan_balance_before
            : round(min((float) ($payment->bill?->amount ?? $payment->amount), $remainingBalance + (float) $payment->amount), 2);

        return [
            'payment' => $payment,
            'previousBalance' => $previousBalance,
            'remainingBalance' => $remainingBalance,
            'loanAllocations' => $payment->payment_type === 'Loan' ? ($payment->loanRepayment?->allocations ?? collect()) : collect(),
        ];
    }

    public function parseReceipt(Request $request, ReceiptOcrService $receiptOcrService): JsonResponse
    {
        try {
            if ($request->user()?->role === 'meter_reader') {
                return response()->json([
                    'ok' => false,
                    'message' => 'You are not authorized to scan payment receipts.',
                    'text' => '',
                    'fields' => [],
                    'missing_fields' => [],
                ], 403);
            }

            if (! $request->user()?->isConsumer() && ! $request->user()?->isAdmin()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'You are not authorized to scan payment receipts.',
                    'text' => '',
                    'fields' => [],
                    'missing_fields' => [],
                ], 403);
            }

            $validated = $request->validate([
                'proof' => ['required', 'file', 'extensions:jpg,jpeg,png,pdf', 'max:4096', new FileMimeType('jpg,jpeg,png,pdf')],
            ]);

            $result = $receiptOcrService->extract($validated['proof']);

            $fields = $result['fields'] ?? [];
            $referenceNumber = $this->validReceiptReference($fields['reference_number'] ?? null);
            $amount = $fields['amount'] ?? null;
            $paymentDate = $fields['payment_date'] ?? null;
            $missingFields = $result['missing_fields'] ?? $this->missingReceiptFields($fields);

            if ($referenceNumber === null) {
                $fields['reference_number'] = null;
            }

            $missingFields = array_values(array_unique(array_merge(
                $missingFields,
                $this->missingReceiptFields($fields),
            )));

            \Log::debug('Receipt parse endpoint result', [
                'ok' => $result['ok'] ?? false,
                'message' => $result['message'] ?? null,
                'text' => $result['text'] ?? '',
                'fields' => $fields,
                'missing_fields' => $missingFields,
            ]);

            if (! ($result['ok'] ?? false) || $missingFields !== [] || blank($referenceNumber) || blank($amount) || blank($paymentDate)) {
                $hasDetectedFields = filled($referenceNumber) || filled($amount) || filled($paymentDate);
                $message = $hasDetectedFields
                    ? 'Some receipt details were detected. Enter the missing details manually: '.implode(', ', $missingFields).'.'
                    : 'The receipt could not be read automatically. Enter the reference number, amount paid, and payment date manually.';

                return response()->json([
                    'ok' => false,
                    'message' => $message,
                    'text' => $result['text'] ?? '',
                    'fields' => $fields,
                    'missing_fields' => $missingFields,
                    'manual_entry_required' => true,
                ], 422);
            }

            try {
                $fields['payment_date'] = now()->parse($paymentDate)->toDateString();
            } catch (\Throwable) {
                return response()->json([
                    'ok' => false,
                    'message' => 'The payment date could not be read automatically. Enter it manually from the receipt.',
                    'text' => $result['text'] ?? '',
                    'fields' => $fields,
                    'missing_fields' => ['Payment Date'],
                    'manual_entry_required' => true,
                ], 422);
            }

            if (! is_numeric($amount) || (float) $amount <= 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'The amount paid could not be read automatically. Enter it manually from the receipt.',
                    'text' => $result['text'] ?? '',
                    'fields' => $fields,
                    'missing_fields' => ['Amount Paid'],
                    'manual_entry_required' => true,
                ], 422);
            }

            if (Payment::query()->where('gateway_reference', $referenceNumber)->exists()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'This reference or transaction number has already been submitted. Check the reference number or enter it manually.',
                    'text' => $result['text'] ?? '',
                    'fields' => $fields,
                    'missing_fields' => [],
                    'manual_entry_required' => true,
                ], 422);
            }

            $result['fields'] = $fields;
            $result['missing_fields'] = [];
            $result['manual_entry_required'] = false;
            $result['message'] = 'Receipt details detected.';

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => 'The uploaded receipt file is invalid.',
                'text' => '',
                'fields' => [],
                'missing_fields' => ['Receipt File'],
                'errors' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            \Log::error('Receipt parse endpoint failed', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Receipt scan failed due to a server-side OCR/API error. Check storage/logs/laravel.log for the exact technical cause.',
                'text' => '',
                'fields' => [],
                'missing_fields' => ['Reference Number', 'Amount Paid', 'Payment Date'],
            ], 500);
        }
    }

    /**
     * Build the searchable consumer directory used by the admin counter-payment
     * form. Disconnected and inactive accounts are deliberately included so
     * their outstanding balances can still be settled before reactivation.
     *
     * @param  Collection<int, Bill>  $availableBills
     * @param  array<int, array<string, mixed>>  $paymentSummaries
     * @param  Collection<int, Loan>  $availableLoans
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildPaymentConsumerDirectory(Collection $availableBills, array $paymentSummaries, Collection $availableLoans): Collection
    {
        $openBillsByConsumer = $availableBills
            ->filter(fn (Bill $bill): bool => ! $bill->balanceIsCarriedForward() && (float) $bill->balance_due > 0)
            ->sortBy(fn (Bill $bill): string => sprintf(
                '%s-%010d',
                $bill->billing_period?->format('Ymd') ?? '99999999',
                $bill->id,
            ))
            ->values()
            ->groupBy('consumer_id');
        $loansByConsumer = $availableLoans->groupBy('consumer_id');

        $statusService = app(ConsumerStatusService::class);

        return Consumer::query()
            ->orderBy('full_name')
            ->get()
            ->map(function (Consumer $consumer) use ($openBillsByConsumer, $loansByConsumer, $paymentSummaries, $statusService): array {
                $consumerBills = $openBillsByConsumer->get($consumer->id, collect());
                $consumerLoans = $loansByConsumer->get($consumer->id, collect());
                $display = $statusService->present($consumer);
                $paymentSummary = $paymentSummaries[$consumer->id] ?? ['active' => false];

                return [
                    'id' => $consumer->id,
                    'name' => $consumer->full_name,
                    'account_number' => $consumer->account_number,
                    'meter_number' => $consumer->meter_number,
                    'status_key' => $display['key'],
                    'status_label' => $display['label'],
                    'status_sub_label' => $display['sub_label'],
                    'status_badge_class' => $display['badge_class'],
                    'status_sub_class' => $display['sub_class'],
                    'status_icon' => $display['icon'],
                    'status_description' => $display['description'],
                    'has_disconnection_notice' => (bool) ($paymentSummary['active'] ?? false),
                    'total_outstanding' => round((float) $consumerBills->sum(fn (Bill $bill): float => (float) $bill->balance_due), 2),
                    'outstanding_loan_balance' => round((float) $consumerLoans->sum('payment_balance'), 2),
                    'loans' => $consumerLoans->map(fn (Loan $loan): array => [
                        'id' => $loan->id,
                        'balance' => $loan->payment_balance,
                        'payment_limit' => $loan->payment_limit,
                        'monthly_installment' => $loan->regularMonthlyInstallment(),
                        'next_installment_due' => (float) ($loan->installments->first(fn ($item) => $item->remaining_balance > 0)?->remaining_balance ?? 0),
                    ])->values()->all(),
                    'bills' => $consumerBills->map(fn (Bill $bill): array => [
                        'id' => $bill->id,
                        'type' => $bill->bill_type,
                        'prn' => $bill->prn,
                        'billing_period' => $bill->billing_period?->format('M Y') ?? 'N/A',
                        'due_date' => $bill->due_date?->format('M d, Y') ?? 'N/A',
                        'amount_due' => round((float) $bill->amount, 2),
                        'balance_due' => round((float) $bill->balance_due, 2),
                        'status' => $bill->status,
                    ])->values()->all(),
                ];
            })
            ->values();
    }

    protected function generateReferenceNumber(): string
    {
        $nextId = (Payment::max('id') ?? 0) + 1;

        return sprintf('PAY-%04d', $nextId);
    }

    protected function missingReceiptFields(array $fields): array
    {
        $missing = [];

        if (! is_numeric($fields['amount'] ?? null) || (float) $fields['amount'] <= 0) {
            $missing[] = 'Amount Paid';
        }

        if ($this->validReceiptReference($fields['reference_number'] ?? null) === null) {
            $missing[] = 'Reference Number';
        }

        if ($this->validReceiptDate($fields['payment_date'] ?? null) === null) {
            $missing[] = 'Payment Date';
        }

        return $missing;
    }

    protected function validReceiptReference(mixed $reference): ?string
    {
        if (! is_string($reference)) {
            return null;
        }

        $candidate = trim($reference);
        $compact = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $candidate) ?? '');

        if (strlen($compact) < 8 || strlen($compact) > 40) {
            return null;
        }

        return $candidate;
    }

    protected function validReceiptAmount(mixed $amount): ?string
    {
        if (! is_numeric($amount) || (float) $amount <= 0) {
            return null;
        }

        return number_format((float) $amount, 2, '.', '');
    }

    protected function validReceiptDate(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        try {
            return now()->parse((string) $date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function buildPaymentNotes(?string $notes, ?string $senderName, ?string $senderPhone): ?string
    {
        $parts = array_filter([
            filled($senderName) ? 'Sender Name: '.trim($senderName) : null,
            filled($senderPhone) ? 'Sender Phone: '.trim($senderPhone) : null,
            filled($notes) ? trim($notes) : null,
        ]);

        if ($parts === []) {
            return null;
        }

        return Str::limit(implode(' | ', $parts), 255, '');
    }

    protected function generateReceiptNumber(): string
    {
        $nextId = (Payment::whereNotNull('receipt_number')->max('id') ?? 0) + 1;

        return sprintf('OR-%s-%04d', now()->format('ymd'), $nextId);
    }

    protected function notifyAdminsOfPendingPayment(Payment $payment): void
    {
        $payment->loadMissing(['bill', 'consumer']);

        User::query()
            ->where('role', 'admin')
            ->get()
            ->each(fn (User $admin) => $admin->notify(new PaymentStatusNotification(
                payment: $payment,
                title: 'Payment pending verification',
                message: sprintf(
                    '%s submitted %s %s for %s.',
                    $payment->consumer->full_name,
                    $payment->payment_channel,
                    $payment->payment_method,
                    $payment->bill?->prn ?? 'Loan #'.$payment->loan_id,
                ),
            )));
    }

    protected function notifyPaymentVerified(Payment $payment): void
    {
        $payment->loadMissing(['bill', 'consumer']);

        $payment->consumer?->user?->notify(new PaymentStatusNotification(
            payment: $payment,
            title: 'Payment verified',
            message: sprintf(
                'Your payment for %s was verified. Receipt %s is now available.',
                $payment->bill?->prn ?? 'Loan #'.$payment->loan_id,
                $payment->receipt_number,
            ),
        ));
    }

    protected function notifyPaymentRejected(Payment $payment): void
    {
        $payment->loadMissing(['bill', 'consumer']);

        $payment->consumer?->user?->notify(new PaymentStatusNotification(
            payment: $payment,
            title: 'Payment rejected',
            message: sprintf(
                'Your payment for %s was rejected. Please review the details and resubmit if needed.',
                $payment->bill?->prn ?? 'Loan #'.$payment->loan_id,
            ),
        ));
    }
}
