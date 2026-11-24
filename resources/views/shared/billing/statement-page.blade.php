@push('styles')
    <style>
        @include('shared.billing.statement-styles')
    </style>
@endpush

<div class="app-shell d-lg-flex stmt-page">
    @include('layouts.sidebar', ['activePage' => 'billing-water'])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading stmt-screen-only">
                <div>
                    <h1>Billing Statement</h1>
                    <p class="mb-0">Payment Reference Number: <strong>{{ $bill->prn }}</strong></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('billing.index', $billingReturnFilters ?? []) }}" class="btn btn-light border">
                        <i class="bi bi-arrow-left me-2"></i>Back to Billing
                    </a>
                    <a href="{{ route('billing.statement.pdf', $bill) }}" class="btn btn-primary">
                        <i class="bi bi-download me-2"></i>Download Bill
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success d-flex align-items-start gap-2 stmt-screen-only" role="alert">
                    <i class="bi bi-check-circle mt-1"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @include('shared.billing.statement-document')
        </div>
    </main>
</div>
