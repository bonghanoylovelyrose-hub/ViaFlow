<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\ConsumerReconnection;
use App\Models\DisconnectionNotice;
use App\Models\Payment;
use App\Support\ReconnectionSchedule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ConsumerDisconnectionHistoryService
{
    public const FILTERS = [
        'all' => 'All History',
        'disconnected' => 'Currently Disconnected',
        'reconnected' => 'Reconnected',
    ];

    public function paginate(string $search, string $status): LengthAwarePaginator
    {
        $consumers = Consumer::query()
            ->select(['id', 'full_name', 'account_number', 'meter_number', 'status', 'disconnected_at', 'reconnected_at', 'reconnection_date', 'reconnection_pending', 'reconnection_scheduled_at', 'full_payment_verified_at'])
            ->where(fn ($query) => $query->inactiveAccounts()
                ->orWhereNotNull('disconnected_at')
                ->orWhereNotNull('reconnected_at')
                ->orWhereHas('disconnectionNotices', fn ($notices) => $notices
                    ->where('status', DisconnectionNotice::STATUS_DISCONNECTED)->orWhereNotNull('disconnected_at'))
                ->orWhereHas('reconnections'))
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('account_number', 'like', "%{$search}%")
                ->orWhere('meter_number', 'like', "%{$search}%")))
            ->with(['disconnectionNotices', 'reconnections.latestAssignedReschedule'])
            ->orderByDesc('id')
            ->get();

        $accountStatuses = app(ConsumerStatusService::class);
        $cycles = $consumers->flatMap(function (Consumer $consumer) use ($accountStatuses, $status) {
            $cycles = $this->forConsumer($consumer)->where('physically_disconnected', true);
            $isInactive = in_array($accountStatuses->key($consumer), [ConsumerStatusService::INACTIVE, ConsumerStatusService::RECONNECTION_PENDING], true);
            if (($isInactive && ! $cycles->contains('reconnected', false)) || ($cycles->isEmpty() && $consumer->reconnected_at !== null)) {
                $cycles->push([
                    'disconnection_date' => null,
                    'reason' => null,
                    'outstanding_balance' => null,
                    'payment_settlement_date' => null,
                    'reconnected' => ! $isInactive,
                    'reconnected_at' => $isInactive ? null : $consumer->reconnected_at,
                ]);
            }
            $currentKey = $isInactive
                ? $cycles->search(fn (array $cycle) => ! $cycle['reconnected'])
                : false;

            return $cycles->map(fn (array $cycle, int $key) => $cycle + [
                'consumer' => $consumer,
                'currently_disconnected' => $key === $currentKey,
            ])->filter(fn (array $cycle) => match ($status) {
                'disconnected' => $cycle['currently_disconnected'],
                'reconnected' => $cycle['reconnected'],
                default => true,
            })->values();
        })->sortByDesc(fn (array $cycle) => $cycle['disconnection_date']?->timestamp ?? PHP_INT_MIN)->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        return (new LengthAwarePaginator($cycles->forPage($page, 20), $cycles->count(), 20, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]))->withQueryString();
    }

    public function currentNotice(Consumer $consumer): ?DisconnectionNotice
    {
        if ($consumer->disconnected_at === null) {
            return null;
        }

        return $consumer->disconnectionNotices()->where('status', DisconnectionNotice::STATUS_DISCONNECTED)
            ->where('disconnected_at', $consumer->disconnected_at)->latest('id')->first();
    }

    public function recordSettlement(Consumer $consumer): void
    {
        $notice = $this->currentNotice($consumer);
        if ($notice === null
            || ConsumerReconnection::query()->where('disconnection_notice_id', $notice->id)->exists()
            || app(DisconnectionNoticeService::class)->outstandingForReconnection($consumer) > 0) {
            return;
        }

        $settledAt = $this->settlementDateFor($consumer);
        if ($settledAt !== null && ($notice->settled_at === null || $notice->settled_at->lessThan($settledAt))) {
            $notice->forceFill(['settled_at' => $settledAt])->save();
        }
    }

    public function settlementDateFor(Consumer $consumer): ?string
    {
        $loanIds = app(DisconnectionNoticeService::class)->disconnectedLoanIds($consumer);
        $payment = Payment::query()->where('consumer_id', $consumer->id)->where('status', 'Verified')
            ->where(fn ($query) => $query->where('payment_type', 'Water')->orWhereIn('loan_id', $loanIds))
            ->when($consumer->disconnected_at !== null, fn ($query) => $query->where('verified_at', '>=', $consumer->disconnected_at))
            ->where('verified_at', '<=', now())
            ->whereHas('verifier', fn ($query) => $query->where('role', 'admin'))->max('verified_at');

        $officeRepayment = \App\Models\LoanRepayment::query()->whereIn('loan_id', $loanIds)->whereNull('payment_id')
            ->when($consumer->disconnected_at, fn ($query) => $query->where('created_at', '>=', $consumer->disconnected_at))
            ->where('created_at', '<=', now())->max('created_at');

        return max($payment, $officeRepayment);
    }

    public function forConsumer(Consumer $consumer): Collection
    {
        $consumer->loadMissing(['disconnectionNotices', 'reconnections.latestAssignedReschedule']);
        $reconnections = $consumer->reconnections->sortBy([['activated_at', 'asc'], ['id', 'asc']]);
        $pendingId = $consumer->reconnection_pending ? $reconnections->last()?->id : null;
        $usedReconnections = collect();
        $history = $consumer->disconnectionNotices->sortBy([['issued_at', 'asc'], ['id', 'asc']])->values()
            ->map(function (DisconnectionNotice $notice) use ($consumer, $reconnections, $pendingId, $usedReconnections): array {
                $reconnection = $reconnections->firstWhere('disconnection_notice_id', $notice->id)
                    ?? $reconnections->first(fn (ConsumerReconnection $record) => ! $usedReconnections->contains($record->id)
                        && $record->disconnection_notice_id === null && $notice->disconnected_at !== null
                        && $record->disconnected_at?->equalTo($notice->disconnected_at));
                if ($reconnection !== null) {
                    $usedReconnections->push($reconnection->id);
                }
                $disconnectedAt = $notice->disconnected_at ?? $reconnection?->disconnected_at;
                $isPending = $pendingId !== null && $reconnection?->id === $pendingId;
                $reconnectedAt = $isPending ? null : $this->actualReconnectionTime($consumer, $disconnectedAt, $reconnection);
                $reconnected = ! $isPending && ($reconnection !== null || $reconnectedAt !== null);

                return [
                    'notice_date' => $notice->issued_at,
                    'disconnection_date' => $disconnectedAt,
                    'reason' => $notice->disconnection_reason ?? $notice->notes,
                    'outstanding_balance' => $notice->outstanding_at_disconnection,
                    'payment_settlement_date' => $reconnection?->payment_settled_at ?? $notice->settled_at,
                    'reconnection_date' => $reconnection?->reconnection_date,
                    'activated_at' => $reconnection?->activated_at,
                    'scheduled_reconnection_at' => $isPending ? $consumer->reconnection_scheduled_at : $this->lastScheduledTime($reconnection),
                    'physically_disconnected' => $notice->status === DisconnectionNotice::STATUS_DISCONNECTED || $disconnectedAt !== null || $reconnection !== null,
                    'reconnected' => $reconnected,
                    'reconnected_at' => $reconnectedAt,
                    'account_status' => match (true) {
                        $isPending => 'Active / Pending Reconnection',
                        $reconnected => 'Active / Connected',
                        default => match ($notice->status) {
                            DisconnectionNotice::STATUS_SETTLED => 'Notice settled before disconnection',
                            DisconnectionNotice::STATUS_ACTIVE => 'Active / Notice issued',
                            default => 'Inactive / Disconnected',
                        },
                    },
                ];
            });

        foreach ($reconnections->whereNotIn('id', $usedReconnections->all()) as $record) {
            $isPending = $record->id === $pendingId;
            $history->push([
                'notice_date' => null,
                'disconnection_date' => $record->disconnected_at,
                'reason' => null,
                'outstanding_balance' => null,
                'payment_settlement_date' => $record->payment_settled_at,
                'reconnection_date' => $record->reconnection_date,
                'activated_at' => $record->activated_at,
                'scheduled_reconnection_at' => $isPending ? $consumer->reconnection_scheduled_at : $this->lastScheduledTime($record),
                'physically_disconnected' => true,
                'reconnected' => ! $isPending,
                'reconnected_at' => $isPending ? null : $this->actualReconnectionTime($consumer, $record->disconnected_at, $record),
                'account_status' => $isPending ? 'Active / Pending Reconnection' : 'Active / Connected',
            ]);
        }

        if ($consumer->disconnected_at !== null
            && ! $history->contains(fn (array $cycle) => $cycle['disconnection_date']?->equalTo($consumer->disconnected_at))) {
            $connected = app(ConsumerStatusService::class)->key($consumer) === ConsumerStatusService::ACTIVE;
            $history->push([
                'notice_date' => null,
                'disconnection_date' => $consumer->disconnected_at,
                'reason' => null,
                'outstanding_balance' => null,
                'payment_settlement_date' => $consumer->full_payment_verified_at,
                'reconnection_date' => $connected ? $consumer->reconnection_date : null,
                'activated_at' => $connected ? $consumer->reconnected_at : null,
                'physically_disconnected' => true,
                'reconnected' => $connected,
                'reconnected_at' => $connected ? $consumer->reconnected_at : null,
                'account_status' => $connected ? 'Active / Connected' : 'Inactive / Disconnected',
            ]);
        }

        return $history->sortByDesc(fn (array $cycle) => ($cycle['disconnection_date'] ?? $cycle['notice_date'] ?? $cycle['activated_at'])?->timestamp ?? 0)->values();
    }

    private function actualReconnectionTime(Consumer $consumer, ?Carbon $disconnectedAt, ?ConsumerReconnection $reconnection): ?Carbon
    {
        if ($reconnection?->completed_at) {
            return $reconnection->completed_at;
        }

        // Older records captured physical restoration in the scheduling field.
        if ($reconnection?->installation_scheduled_at?->lessThanOrEqualTo($reconnection->activated_at)) {
            return $reconnection->installation_scheduled_at;
        }

        if ($disconnectedAt !== null && $consumer->disconnected_at?->equalTo($disconnectedAt)
            && $consumer->reconnected_at?->greaterThanOrEqualTo($disconnectedAt)) {
            return $consumer->reconnected_at;
        }

        return null;
    }

    private function lastScheduledTime(?ConsumerReconnection $reconnection): ?Carbon
    {
        $reschedule = $reconnection?->completed_at ? $reconnection->latestAssignedReschedule : null;

        return $reschedule
            ? ReconnectionSchedule::parse($reschedule->new_installation_date->toDateString().' '.$reschedule->new_installation_time)
            : $reconnection?->installation_scheduled_at;
    }
}
