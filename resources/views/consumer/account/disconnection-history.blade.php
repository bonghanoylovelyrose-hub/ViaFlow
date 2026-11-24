<section class="panel-card p-4 mt-4" aria-labelledby="disconnectionHistoryTitle">
    <h2 class="section-title mb-3" id="disconnectionHistoryTitle">Disconnection History</h2>
    <p class="small text-secondary">Each row retains the account status and recorded dates for that notice or disconnection cycle.</p>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Disconnection Date</th>
                    <th>Reason</th>
                    <th>Outstanding Balance at Disconnection</th>
                    <th>Payment Settlement Date</th>
                    <th>Reconnection Date</th>
                    <th>Account Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($disconnectionHistory as $cycle)
                    <tr>
                        <td>
                            {{ $cycle['disconnection_date']?->format('M d, Y g:i A') ?? 'Not recorded / not disconnected' }}
                            @if ($cycle['notice_date'])
                                <div class="small text-secondary">Notice: {{ $cycle['notice_date']->format('M d, Y g:i A') }}</div>
                            @endif
                        </td>
                        <td>{{ $cycle['reason'] ?: 'Not recorded' }}</td>
                        <td>{{ $cycle['outstanding_balance'] === null ? 'Not recorded' : 'PHP '.number_format((float) $cycle['outstanding_balance'], 2) }}</td>
                        <td>{{ $cycle['payment_settlement_date']?->format('M d, Y g:i A') ?? 'Not recorded' }}</td>
                        <td>
                            {{ \App\Support\ReconnectionSchedule::format($cycle['reconnected_at'] ?? null, 'M d, Y g:i A') ?? 'Not recorded' }}
                            @if (!empty($cycle['scheduled_reconnection_at']))
                                <div class="small text-secondary">Scheduled: {{ \App\Support\ReconnectionSchedule::format($cycle['scheduled_reconnection_at']) }} (Philippine time)</div>
                            @endif
                            @if ($cycle['activated_at'])
                                <div class="small text-secondary">Activated: {{ $cycle['activated_at']->format('M d, Y g:i A') }}</div>
                            @endif
                        </td>
                        <td>{{ $cycle['account_status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">No disconnection history on record.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
