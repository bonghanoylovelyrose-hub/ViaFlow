@php($history = $evaluation?->evaluated_values ?? [])
@if (array_key_exists('overdue_water_months', $history))
    <div class="dss-detail-section">
        <h3>Payment History Evidence</h3>
        <div class="dss-detail-grid">
            <div><span class="dss-detail-label">Late payment records in the last six months</span><strong class="dss-detail-value">{{ $history['late_payments_last_six_months'] ?? 0 }}</strong></div>
            <div><span class="dss-detail-label">Overdue water months</span><strong class="dss-detail-value">{{ $history['overdue_water_months'] }}</strong></div>
            <div><span class="dss-detail-label">Overdue water balance</span><strong class="dss-detail-value">₱{{ number_format((float) ($history['overdue_water_balance'] ?? 0), 2) }}</strong></div>
            <div><span class="dss-detail-label">Previous loan balance</span><strong class="dss-detail-value">₱{{ number_format((float) ($history['previous_loan_outstanding_balance'] ?? 0), 2) }}</strong></div>
            <div><span class="dss-detail-label">Recorded disconnection notices</span><strong class="dss-detail-value">{{ count($history['disconnection_notice_history'] ?? []) }}</strong></div>
        </div>
        @if (! empty($history['late_payment_history']))
            <div class="dss-detail-label mt-3">Verified late payments</div>
            <ul class="dss-detail-list">@foreach ($history['late_payment_history'] as $entry) @foreach ($entry['late_payments'] as $payment)<li>{{ $entry['billing_period'] ?? 'Period not recorded' }}: paid {{ $payment['payment_date'] }}, {{ $payment['days_late'] }} day(s) late.</li>@endforeach @endforeach</ul>
        @endif
        @if (! empty($history['overdue_water_history']))
            <div class="dss-detail-label mt-3">Overdue billing months</div>
            <ul class="dss-detail-list">@foreach ($history['overdue_water_history'] as $entry)<li>{{ $entry['billing_period'] ?? 'Period not recorded' }}: ₱{{ number_format((float) $entry['amount'], 2) }} remaining, {{ $entry['days_overdue'] }} day(s) past the configured grace period.</li>@endforeach</ul>
        @endif
    </div>
@endif
