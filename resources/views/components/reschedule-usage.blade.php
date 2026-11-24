@props(['usage', 'type' => 'installation'])
<div class="small mt-2 mb-3" data-reschedule-used="{{ $usage['used'] }}" data-reschedule-remaining="{{ $usage['remaining'] }}">
    <strong>{{ $usage['used'] }} of {{ $usage['limit'] }} used</strong>
    &middot; {{ $usage['remaining'] }} reschedule attempt{{ $usage['remaining'] === 1 ? '' : 's' }} remaining.
    @if (! $usage['available'])
        <div class="fw-semibold">Reschedule limit reached. No further {{ $type }} rescheduling is allowed.</div>
    @endif
</div>
