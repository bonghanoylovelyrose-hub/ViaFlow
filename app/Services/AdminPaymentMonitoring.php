<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\LoanRepayment;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminPaymentMonitoring
{
    public const TRANSACTION_DATE = "CASE WHEN payments.status = 'Verified' THEN payments.payment_date ELSE COALESCE(payments.created_at, payments.payment_date) END";

    public static function formatTimestamp(?CarbonInterface $timestamp): string
    {
        return $timestamp?->copy()->timezone('Asia/Manila')->format('M d, Y · g:i A') ?? 'Not recorded';
    }

    public static function billingPeriods(?Bill $bill, ?LoanRepayment $repayment = null): Collection
    {
        $periods = $repayment?->allocations->pluck('installment.billing_period')->filter();

        if ($periods?->isNotEmpty()) {
            return $periods->sort()->map(fn (CarbonInterface $period) => $period->format('M Y'))->unique();
        }

        $period = ($bill?->bill_type === 'Loan' ? $bill->loanInstallment?->billing_period : null) ?? $bill?->billing_period;

        return $period ? collect([$period->format('M Y')]) : collect();
    }

    public function years(string $recordType): Collection
    {
        $dates = Payment::query()->where('payment_type', $recordType)
            ->selectRaw(self::TRANSACTION_DATE.' as transaction_date')->distinct()->pluck('transaction_date');

        if ($recordType === 'Loan') {
            $dates = $dates->concat(LoanRepayment::query()->whereNull('payment_id')->distinct()->pluck('payment_date'));
        }

        return $dates->filter()->map(fn ($date) => (int) substr((string) $date, 0, 4))->unique()->sortDesc()->values();
    }

    public function accounts(Collection $rows, Request $request, array $filters): LengthAwarePaginator
    {
        $groups = $rows->groupBy(fn (array $entry) => $entry['kind'] === 'loan-repayment'
            ? $entry['record']->loan?->consumer_id
            : $entry['record']->consumer_id);
        $accountPage = LengthAwarePaginator::resolveCurrentPage();
        $accounts = new LengthAwarePaginator(
            $groups->forPage($accountPage, 10),
            $groups->count(),
            10,
            $accountPage,
            ['path' => $request->url(), 'query' => $filters],
        );

        return $accounts->through(function (Collection $entries, int|string $accountId) use ($request, $filters, $accountPage): array {
            $latest = $entries->first();
            $consumer = $latest['kind'] === 'loan-repayment'
                ? $latest['record']->loan?->consumer
                : $latest['record']->consumer;
            $id = $accountId === '' ? 'unknown' : $accountId;
            $expanded = (string) $request->query('payment_account') === (string) $id;
            $perPage = 6;
            $page = $expanded ? min(max(1, $request->integer('history_page')), (int) ceil($entries->count() / $perPage)) : 1;

            return [
                'id' => $id,
                'consumer' => $consumer,
                'latest_date' => $latest['date'],
                'expanded' => $expanded,
                'history' => new LengthAwarePaginator(
                    $entries->forPage($page, $perPage)->values(),
                    $entries->count(),
                    $perPage,
                    $page,
                    [
                        'path' => $request->url(),
                        'pageName' => 'history_page',
                        'query' => $filters + ['page' => $accountPage, 'payment_account' => $id],
                        'fragment' => 'payment-account-'.$id,
                    ],
                ),
            ];
        });
    }

    public function filterByDate(Builder $query, string $column, int $year, int $month): void
    {
        $date = DB::raw($column);
        $query->when($year, fn ($query) => $query->whereYear($date, $year))
            ->when($month, fn ($query) => $query->whereMonth($date, $month));
    }

    public function filterSearch(Builder $query, string $search, string $consumerRelation, array $referenceColumns = []): void
    {
        $pattern = '%'.strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

        $query->where(function (Builder $records) use ($pattern, $consumerRelation, $referenceColumns): void {
            $records->whereHas('bill', fn (Builder $bill) => $bill->whereRaw("LOWER(prn) LIKE ? ESCAPE '!'", [$pattern]))
                ->orWhereHas($consumerRelation, fn (Builder $consumer) => $consumer
                    ->whereRaw("LOWER(full_name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(account_number) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(meter_number) LIKE ? ESCAPE '!'", [$pattern]));

            foreach ($referenceColumns as $column) {
                $records->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }
}
