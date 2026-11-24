<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DisconnectionRecordsService
{
    private const PER_PAGE = 2;

    public const FILTERS = [
        '' => 'Active Notices',
        'grace' => 'Within Grace Period',
        'ready' => 'Ready for Disconnection',
        'paid' => 'Resolved',
        'unpaid' => 'Unpaid Accounts',
    ];

    public function activeFor(Consumer $consumer): ?array
    {
        return $this->paginate('', '', consumerId: $consumer->id)->getCollection()
            ->first(fn (array $record): bool => $record['has_active_notice']);
    }

    public function paginate(string $search, string $status, string $membership = '', ?int $consumerId = null): LengthAwarePaginator
    {
        $asOf = now();
        $payments = Payment::query()->select('bill_id')
            ->when($consumerId !== null, fn ($query) => $query->where('payments.consumer_id', $consumerId))
            ->selectRaw('SUM(amount) as total')->where('status', 'Verified')->where('payment_type', 'Water')->groupBy('bill_id');
        $balances = Bill::query()->forCollection()->select('bills.consumer_id')
            ->leftJoinSub($payments, 'verified', 'verified.bill_id', '=', 'bills.id')
            ->where('bills.bill_type', 'Water')
            ->when($consumerId !== null, fn ($query) => $query->where('bills.consumer_id', $consumerId))
            ->whereNull('bills.carried_forward_to_bill_id')
            ->selectRaw('SUM(CASE WHEN bills.amount > COALESCE(verified.total, 0) + bills.waived_reconnection_fee_amount THEN bills.amount - COALESCE(verified.total, 0) - bills.waived_reconnection_fee_amount ELSE 0 END) as outstanding')
            ->groupBy('bills.consumer_id');
        $activeNotices = $this->visibleNotices()->active()->select('consumer_id')
            ->when($consumerId !== null, fn ($query) => $query->where('disconnection_notices.consumer_id', $consumerId))
            ->selectRaw('MAX(deadline_at) as deadline, MAX(issued_at) as issued_at, MAX(id) as notice_id')
            ->selectRaw('MAX(CASE WHEN source = ? AND is_billing_fixture = 0 THEN 1 ELSE 0 END) as has_water', [DisconnectionNotice::SOURCE_WATER])
            ->selectRaw('MAX(CASE WHEN is_billing_fixture = 1 THEN outstanding_amount ELSE 0 END) as demo_balance')
            ->selectRaw('SUM(CASE WHEN source = ? THEN loan_balance_due ELSE 0 END) as loan_balance', [DisconnectionNotice::SOURCE_LOAN])
            ->groupBy('consumer_id');
        $settledNotices = DisconnectionNotice::query()->where('status', DisconnectionNotice::STATUS_SETTLED)
            ->when($consumerId !== null, fn ($query) => $query->where('disconnection_notices.consumer_id', $consumerId))
            ->select('consumer_id')->selectRaw('MAX(settled_at) as resolved_at')->groupBy('consumer_id');
        $waterBalance = 'CASE WHEN notice_totals.has_water = 1 THEN COALESCE(balances.outstanding, 0) ELSE COALESCE(notice_totals.demo_balance, 0) END';
        $loanBalance = 'COALESCE(notice_totals.loan_balance, 0)';
        $outstanding = "($waterBalance + $loanBalance)";

        $query = Consumer::query()->select('consumers.*')
            ->selectRaw("$waterBalance as water_outstanding, $loanBalance as loan_outstanding, $outstanding as outstanding")
            ->leftJoinSub($balances, 'balances', 'balances.consumer_id', '=', 'consumers.id')
            ->leftJoinSub($activeNotices, 'notice_totals', 'notice_totals.consumer_id', '=', 'consumers.id')
            ->leftJoinSub($settledNotices, 'settlements', 'settlements.consumer_id', '=', 'consumers.id')
            ->activeAccounts()
            ->where('consumers.reconnection_pending', false)
            ->when($consumerId !== null, fn ($query) => $query->where('consumers.id', $consumerId))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('consumers.full_name', 'like', "%{$search}%")
                    ->orWhere('consumers.account_number', 'like', "%{$search}%")
                    ->orWhere('consumers.meter_number', 'like', "%{$search}%");
            }))
            ->when($membership === 'member', fn ($query) => $query->where('consumers.membership_type', 'Member'))
            ->when($membership === 'non_member', fn ($query) => $query->where('consumers.membership_type', 'Non-Member'))
            ->with(['reconnections', 'disconnectionNotices' => fn ($query) => $this->includeFixtures($query->getQuery())
                ->with(['bill', 'confirmedBy'])->orderByDesc('issued_at')->orderByDesc('id')]);

        if ($status === 'paid') {
            $query->whereNull('notice_totals.consumer_id')->whereNotNull('settlements.consumer_id');
        } else {
            $query->whereRaw("$outstanding > 0");
            if (in_array($status, ['grace', 'ready'], true)) {
                $query->where('notice_totals.deadline', $status === 'ready' ? '<=' : '>', $asOf);
            }
        }

        $query->orderByRaw('CASE WHEN notice_totals.deadline <= ? THEN 0 WHEN notice_totals.consumer_id IS NOT NULL THEN 1 ELSE 2 END', [$asOf])
            ->orderByDesc('notice_totals.issued_at')
            ->orderByDesc('notice_totals.notice_id')
            ->orderByDesc('settlements.resolved_at')
            ->orderByDesc('consumers.id');

        $records = $query->paginate(self::PER_PAGE, ['*'], 'notice_page');
        if ($records->currentPage() > $records->lastPage()) {
            $records = $query->paginate(self::PER_PAGE, ['*'], 'notice_page', page: 1, total: $records->total());
        }
        $records->withQueryString()->fragment('active-disconnection-notices');

        $records->getCollection()->transform(function (Consumer $consumer) use ($asOf): array {
            $active = $consumer->disconnectionNotices->filter(fn ($notice) => $notice->isActive())->sortByDesc('deadline_at')->first();
            $settled = $consumer->disconnectionNotices->where('status', DisconnectionNotice::STATUS_SETTLED)->sortByDesc('settled_at');
            $notice = $active ?? $settled->first();
            $outstanding = round((float) $consumer->outstanding, 2);
            $activeDeadline = $active?->deadline_at;
            $windowElapsed = $activeDeadline !== null && $activeDeadline->lte($asOf);
            $ready = $outstanding > 0 && $windowElapsed;
            $sources = $active ? [] : $settled->where('settled_at', $notice?->settled_at)->pluck('source')->all();

            return [
                'consumer' => $consumer,
                'total_outstanding' => $outstanding,
                'water_outstanding' => (float) $consumer->water_outstanding,
                'loan_outstanding' => (float) $consumer->loan_outstanding,
                'reason' => DisconnectionNotice::reasonForSources(
                    $consumer->water_outstanding > 0 || in_array(DisconnectionNotice::SOURCE_WATER, $sources, true),
                    $consumer->loan_outstanding > 0 || in_array(DisconnectionNotice::SOURCE_LOAN, $sources, true),
                ),
                'notice_date' => $notice?->issued_at,
                'payment_deadline' => $notice?->deadline_at,
                'payment_window_elapsed' => $windowElapsed,
                'notice_billing_period' => $notice?->bill?->billing_period,
                'notice_status' => $notice?->status,
                'has_active_notice' => $active !== null,
                'can_confirm_disconnection' => $ready && ! $active?->is_billing_fixture,
                'is_billing_fixture' => (bool) $active?->is_billing_fixture,
                'record_status' => match (true) {
                    ! $active => 'Resolved',
                    $ready => 'Ready for Disconnection',
                    $activeDeadline !== null => 'Within Grace Period',
                    default => 'Unpaid',
                },
                'history' => $consumer->disconnectionNotices,
            ];
        });

        return $records;
    }

    private function visibleNotices(): Builder
    {
        return $this->includeFixtures(DisconnectionNotice::query());
    }

    private function includeFixtures(Builder $query): Builder
    {
        if (! app()->isLocal()) {
            return $query;
        }

        return $query->withBillingFixtures()->where(function (Builder $query): void {
            $query->where('disconnection_notices.is_billing_fixture', false)
                ->orWhereNotExists(function ($notice): void {
                    $notice->selectRaw('1')->from('disconnection_notices as live_notice')
                        ->whereColumn('live_notice.consumer_id', 'disconnection_notices.consumer_id')
                        ->where('live_notice.status', DisconnectionNotice::STATUS_ACTIVE)
                        ->where('live_notice.is_billing_fixture', false);
                });
        });
    }
}
