@php
    $activePage = 'billing-loan';
    $isAdmin = auth()->user()->isAdmin();
    $summary = $loanBillingSummary;
    $hasLoanBillingFilters = array_filter($loanBillingFilters, fn ($value) => $value !== '') !== [];
@endphp

<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Loan Billing</h1>
                    <p>Monthly installments from the saved repayment schedule. Upcoming months are shown before they are billed.</p>
                </div>
                <a href="{{ route('payments.index', ['view' => 'loan-payments']) }}" class="btn btn-primary">View Loan Payments</a>
            </div>

            @if ($loanSchedulesNeedReviewCount > 0)
                <div class="alert alert-warning" role="status">{{ $loanSchedulesNeedReviewCount === 1 ? 'An approved loan needs' : $loanSchedulesNeedReviewCount.' approved loans need' }} schedule review. A complete saved schedule and an approval or billing start date are required to display installments.</div>
            @endif
            @if (($loanScheduleSynchronization['skipped'] ?? 0) > 0)
                <div class="alert alert-warning" role="status">Some loan billing records need administrator review before synchronization can be completed. Existing payment records have been retained.</div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-xl-3"><div class="panel-card p-3 h-100"><div class="small text-secondary">Scheduled Installments</div><div class="h3 mb-0">{{ number_format($summary['bill_count']) }}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="panel-card p-3 h-100"><div class="small text-secondary">Total Scheduled Amount</div><div class="h3 mb-0">₱{{ number_format($summary['total_due'], 2) }}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="panel-card p-3 h-100"><div class="small text-secondary">Verified / Recorded Paid</div><div class="h3 mb-0">{{ ! empty($loanScheduleSynchronization['review_loan_ids']) || $loanSchedulesNeedReviewCount > 0 ? 'Under review' : '₱'.number_format($summary['total_paid'], 2) }}</div></div></div>
                <div class="col-12 col-sm-6 col-xl-3"><div class="panel-card p-3 h-100"><div class="small text-secondary">Loan Balance</div><div class="h3 mb-0">{{ ! empty($loanScheduleSynchronization['review_loan_ids']) || $loanSchedulesNeedReviewCount > 0 ? 'Under review' : '₱'.number_format($summary['remaining_balance'], 2) }}</div></div></div>
            </div>

            <section class="panel-card p-4" aria-labelledby="loan-billing-records-title">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1" id="loan-billing-records-title">{{ $isAdmin ? 'Consumer Loans' : 'Loan Installment Bills' }}</h2>
                        <p class="text-secondary small mb-0">Amounts paid include verified payments and recorded office repayments. An upcoming installment is not billed again if it is already fully paid.</p>
                    </div>
                    @if ($summary['bill_count'] === 0)
                        <span class="badge text-bg-light border">No loan bills generated yet</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('billing.loan') }}" class="row g-2 align-items-end mb-3" data-auto-filter data-auto-filter-debounce="450">
                    <div class="{{ $isAdmin ? 'col-12 col-xl' : 'col-12 col-lg-4' }}">
                        <label @class(['form-label', 'text-uppercase small fw-bold' => $isAdmin]) for="loan-billing-search">{{ $isAdmin ? 'Search Consumer / Account / Meter Number' : 'Search' }}</label>
                        <input type="search" class="form-control" id="loan-billing-search" name="search" value="{{ $loanBillingFilters['search'] }}" placeholder="{{ $isAdmin ? 'Search by name, account, or meter number...' : 'Consumer, account, PRN, or installment' }}">
                    </div>
                    <div class="{{ $isAdmin ? 'col-12 col-sm-6 col-xl-3' : 'col-12 col-sm-6 col-lg-3' }}">
                        <label class="form-label text-uppercase small fw-bold" for="loan-billing-year">Period</label>
                        <x-period-picker id="loan-billing-year" month-id="loan-billing-month" :years="$loanBillingYears"
                            :selected-year="$loanBillingFilters['year']" :selected-month="$loanBillingFilters['month']" />
                    </div>
                    <div class="{{ $isAdmin ? 'col-12 col-sm-6 col-xl-3' : 'col-8 col-sm-6 col-lg-3' }}">
                        @if ($isAdmin)
                            <label class="form-label text-uppercase small fw-bold" for="loan-billing-status">Payment Status</label>
                        @endif
                        <div class="d-flex align-items-center gap-2 status-filter-display" style="min-width:0">
                            <select aria-label="Status filter" data-status-filter data-clearable-filter data-filter-default="{{ $isAdmin ? 'All Status' : 'All statuses' }}" style="min-width:0;flex:1 1 0" class="form-select" id="loan-billing-status" name="status">
                                @foreach ($loanBillingStatuses as $status)
                                    <option value="{{ $status }}" @selected($loanBillingFilters['status'] === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @if ($isAdmin)
                                <a href="{{ route('billing.loan') }}" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear Loan Billing filters">Clear</a>
                            @else
                                <button type="submit" name="status" value="" class="btn btn-outline-primary btn-sm flex-shrink-0" aria-label="Clear status filter">Clear</button>
                            @endif
                        </div>
                    </div>
                    @if (! $isAdmin && $hasLoanBillingFilters)
                        <div class="col-4 col-lg-2"><a href="{{ route('billing.loan') }}" class="btn btn-light border w-100">Clear filters</a></div>
                    @endif
                    <noscript><button class="btn btn-primary" type="submit">Apply filters</button></noscript>
                </form>

                @if (! $isAdmin)
                    @include('shared.billing.loan-installments', ['installments' => $loanBills])
                @elseif ($loanBills->isNotEmpty())
                    @include('admin.billing.loan-groups')
                @else
                    <div class="text-center py-5 text-secondary">
                        @if ($summary['bill_count'] === 0)
                            Loan balance: <strong>₱0.00</strong><div class="mt-1">No loan bills generated yet.</div>
                        @else
                            No loans match these filters.
                        @endif
                    </div>
                @endif
                @if ($loanBills->hasPages())
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                        <span class="small text-secondary">Showing {{ $loanBills->firstItem() }}–{{ $loanBills->lastItem() }} of {{ $loanBills->total() }}</span>
                        {{ $loanBills->links() }}
                    </div>
                @endif
            </section>
        </div>
    </main>
</div>
