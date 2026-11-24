<dl class="row mb-3">
    <dt class="col-sm-6">Consumer Name</dt>
    <dd class="col-sm-6">{{ $accountSummary['consumer_name'] }}</dd>
    <dt class="col-sm-6">Account Number</dt>
    <dd class="col-sm-6">{{ $accountSummary['account_number'] }}</dd>
    <dt class="col-sm-6">Meter Number</dt>
    <dd class="col-sm-6">{{ $accountSummary['meter_number'] }}</dd>
    <dt class="col-sm-6">Account Status</dt>
    <dd class="col-sm-6"><span class="badge bg-secondary">{{ $accountSummary['account_status'] }}</span></dd>
    <dt class="col-sm-6">Outstanding Water Balance</dt>
    <dd class="col-sm-6" data-account-balance="outstanding_water_balance">PHP {{ number_format($accountSummary['outstanding_water_balance'], 2) }}</dd>
    <dt class="col-sm-6">Outstanding Loan Balance</dt>
    <dd class="col-sm-6" data-account-balance="outstanding_loan_balance">PHP {{ number_format($accountSummary['outstanding_loan_balance'], 2) }}</dd>
    <dt class="col-sm-6">Total Amount to Pay</dt>
    <dd class="col-sm-6 fw-bold" data-account-balance="total_amount_to_pay">PHP {{ number_format($accountSummary['total_amount_to_pay'], 2) }}</dd>
</dl>
<p>Please visit the office to settle any outstanding water balance in full and arrange authorized reactivation. Loan payments are recorded separately. Dashboard access returns after your water service is reactivated.</p>
