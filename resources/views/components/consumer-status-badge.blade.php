@props([
    'consumer',
    'showApplicationStage' => false,
])

@php
    $display = app(\App\Services\ConsumerStatusService::class)->present($consumer);
    if ($showApplicationStage && $consumer->status === 'Pending Verification') {
        $display['sub_label'] = $consumer->status;
    }
@endphp

<span {{ $attributes->class(['consumer-service-status'])->merge(['title' => $display['description']]) }}>
    <span class="badge consumer-status-badge {{ $display['badge_class'] }}">
        <i class="bi {{ $display['icon'] }}" aria-hidden="true"></i>
        <span>{{ $display['label'] }}</span>
    </span>
    <span class="consumer-status-sub {{ $display['sub_class'] }}">{{ $display['sub_label'] }}</span>
</span>
