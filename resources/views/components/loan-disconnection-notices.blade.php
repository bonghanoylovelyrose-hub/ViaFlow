@props(['consumer'])

@foreach ($consumer?->disconnectionNotices()->active()->where('source', \App\Models\DisconnectionNotice::SOURCE_LOAN)->orderBy('deadline_at')->get() ?? [] as $notice)
    <div class="alert alert-danger d-flex align-items-start gap-2 mt-3" role="alert">
        <i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i>
        <div>
            <strong>{{ \App\Services\DisconnectionNoticeService::LOAN_NOTICE_SUBJECT }}</strong>
            <div>Outstanding loan balance and overdue amount: <strong>PHP {{ number_format((float) $notice->loan_balance_due, 2) }}</strong></div>
            <div>Notice issued {{ $notice->issued_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }}</div>
            <div>Payment deadline <strong>{{ $notice->deadline_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }}</strong> Philippine time</div>
            <div>{{ $notice->paymentWindowHasElapsed() ? 'The payment deadline has passed. Settle the full balance before physical disconnection.' : 'Settle the full meter loan balance and have the payment verified within three days to avoid possible disconnection.' }}</div>
        </div>
    </div>
@endforeach
