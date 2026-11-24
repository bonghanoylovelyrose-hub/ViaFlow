@php
    $consumer = $notice['consumer'];
    $modalId = 'disconnectionNoticeDetails-'.$consumer->id;
    $usage = app(\App\Services\InstallationRescheduleService::class)->usage($consumer);
    $formatDate = static fn ($date) => \App\Support\ReconnectionSchedule::format($date, 'M d, Y g:i A') ?? 'Unavailable';
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header bg-light px-3 px-sm-4">
                <div>
                    <h2 class="modal-title fs-5" id="{{ $modalId }}-title">Disconnection Notice Details</h2>
                    <div class="small text-secondary">{{ $consumer->full_name }} · Account {{ $consumer->account_number ?: 'Pending assignment' }} · Meter {{ $consumer->meter_number ?: 'Pending assignment' }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-sm-4">
                <section class="border rounded-3 p-3 mb-3">
                    <h3 class="h6 fw-bold">Notice overview</h3>
                    <dl class="row g-3 mb-0">
                        <div class="col-12">
                            <dt class="small text-secondary">Disconnection status</dt>
                            <dd class="mb-0">{{ $notice['record_status'] }}</dd>
                        </div>
                        <div class="col-sm-6">
                            <dt class="small text-secondary">Disconnection reason</dt>
                            <dd class="mb-0">{{ $notice['reason'] }}</dd>
                        </div>
                        <div class="col-sm-6">
                            <dt class="small text-secondary">Outstanding balance</dt>
                            <dd class="mb-0">P {{ number_format($notice['total_outstanding'], 2) }}</dd>
                        </div>
                        <div class="col-sm-6">
                            <dt class="small text-secondary">Notice date</dt>
                            <dd class="mb-0">{{ $formatDate($notice['notice_date']) }}</dd>
                        </div>
                        <div class="col-sm-6">
                            <dt class="small text-secondary">Payment deadline</dt>
                            <dd class="mb-0">{{ $formatDate($notice['payment_deadline']) }}</dd>
                        </div>
                    </dl>
                    @if ($notice['has_active_notice'])
                        <div class="small text-secondary mt-3">
                            @if ($notice['payment_deadline'])
                                {{ $notice['payment_window_elapsed'] ? 'Three-day window elapsed.' : 'Three-day window open.' }}
                            @endif
                            Full payment required before disconnection.
                        </div>
                    @endif
                </section>

                <section class="border rounded-3 p-3 mb-3">
                    <h3 class="h6 fw-bold mb-0">Reschedule attempts</h3>
                    <x-reschedule-usage :usage="$usage" />
                </section>

                <div class="small text-secondary mb-2">Notice and reconnection times are shown in Philippine time.</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <section class="border rounded-3 p-3 h-100">
                            <h3 class="h6 fw-bold">Notice history ({{ $notice['history']->count() }})</h3>
                            <ul class="list-group list-group-flush small">
                                @forelse ($notice['history'] as $history)
                                    <li class="list-group-item px-0">
                                        <strong>{{ $history->status }}</strong>
                                        <div>Issued {{ $formatDate($history->issued_at) }}</div>
                                        <div>Deadline: {{ $formatDate($history->deadline_at) }}</div>
                                        @if ($history->settled_at)
                                            <div>Paid: {{ $formatDate($history->settled_at) }}</div>
                                        @endif
                                        @if ($history->disconnected_at)
                                            <div>Disconnected: {{ $formatDate($history->disconnected_at) }}</div>
                                            <div>Confirmed by {{ $history->confirmedBy?->name ?: 'Admin' }}</div>
                                        @endif
                                    </li>
                                @empty
                                    <li class="list-group-item px-0 text-secondary">No notice history.</li>
                                @endforelse
                            </ul>
                        </section>
                    </div>
                    <div class="col-md-6">
                        <section class="border rounded-3 p-3 h-100">
                            <h3 class="h6 fw-bold">Reconnection history ({{ $consumer->reconnections->count() }})</h3>
                            <ul class="list-group list-group-flush small">
                                @forelse ($consumer->reconnections as $reconnection)
                                    <li class="list-group-item px-0">
                                        <div><strong>Activated:</strong> {{ $formatDate($reconnection->activated_at) }}</div>
                                        <div>Originally scheduled: {{ $formatDate($reconnection->installation_scheduled_at) }}</div>
                                        @if ($reconnection->completed_at)
                                            <div>Completed: {{ $formatDate($reconnection->completed_at) }}</div>
                                        @endif
                                    </li>
                                @empty
                                    <li class="list-group-item px-0 text-secondary">No reconnection history.</li>
                                @endforelse
                            </ul>
                        </section>
                    </div>
                </div>
            </div>
            <div class="modal-footer px-3 px-sm-4">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
