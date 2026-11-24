@if ($notice)
    <div class="alert {{ $notice['payment_window_elapsed'] ? 'alert-danger' : 'alert-warning' }}" role="status" data-notice-status="{{ $notice['record_status'] }}">
        <strong>{{ $notice['reason'] }}</strong>
        <div>{{ $notice['record_status'] }}</div>
        <div>Overdue water balance <strong>&#8369;{{ number_format($notice['water_outstanding'], 2) }}</strong></div>
        @if ($notice['notice_date'])
            <div>Notice issued {{ $notice['notice_date']->copy()->timezone('Asia/Manila')->format('F d, Y g:i A') }} Philippine time</div>
        @endif
        @if ($notice['payment_deadline'])
            <div>Payment deadline {{ $notice['payment_deadline']->copy()->timezone('Asia/Manila')->format('F d, Y g:i A') }} Philippine time</div>
        @endif
    </div>
@endif
