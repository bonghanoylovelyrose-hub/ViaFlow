@php($eligibility = $reconnectionEligibility[$consumer->id])
@if ($eligibility['eligible'])
    <span class="small text-success fw-semibold me-2">Ready for Reconnection</span>
@endif
<button type="button" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#consumerReconnectModal"
    data-reconnect-url="{{ route('consumers.reconnect', $consumer) }}" data-reconnect-name="{{ $consumer->full_name }}"
    data-reconnect-account="{{ $consumer->account_number }}" data-reconnect-meter="{{ $consumer->meter_number }}"
    data-original-installation-date="{{ $consumer->installation_date?->format('M d, Y') ?? 'Not recorded' }}"
    data-reconnect-balance="{{ number_format($eligibility['outstanding'], 2) }}" @disabled(! $eligibility['eligible'])>
    <i class="bi bi-plug me-1"></i>Reconnect
</button>
