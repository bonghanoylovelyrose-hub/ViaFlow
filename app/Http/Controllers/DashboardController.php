<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Announcement;
use App\Models\MeterReading;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use App\Services\ConsumerBillingScenario;
use App\Services\ConsumerDisconnectionHistoryService;
use App\Services\Dashboard\AdminDashboardService;
use App\Services\Dashboard\DashboardPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AdminDashboardService $adminDashboard, ConsumerBillingScenario $scenario): View
    {
        $user = $request->user();
        $hasBillingScenario = $user->isConsumer() && $scenario->isAvailableFor($user->consumer);
        $billingScenario = $hasBillingScenario && $request->query('ledger') !== 'recorded' ? $scenario->snapshot($user->consumer) : null;
        if (! $billingScenario) {
            $this->syncOpenBillStatuses();
        }

        if ($user->isConsumer()) {
            if (! $billingScenario) {
                $user->consumer?->syncAccountStanding();
            }
            $data = $billingScenario
                ? $this->buildScenarioDashboard($user, $billingScenario)
                : $this->buildConsumerDashboard($user, app(ConsumerBalanceService::class));
            $data['billingScenario'] = $billingScenario;
            $data['hasBillingScenario'] = $hasBillingScenario;
            $data['announcements'] = Schema::hasTable('announcements')
                ? app(\App\Services\AnnouncementAudienceService::class)->applyVisibility(Announcement::query(), $user)
                    ->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id')->limit(3)->get()
                : collect();
            return view('consumer.dashboard', $data);
        }

        if ($user->isMeterReader()) {
            return view('meter-reading.dashboard', $this->buildMeterReaderDashboard($user));
        }

        $period = DashboardPeriod::allAvailable();

        return view('admin.dashboard', [
            'period' => $period,
            'dashboard' => $adminDashboard->build($period),
        ]);
    }

    private function buildScenarioDashboard(User $user, array $scenario): array
    {
        $consumer = $user->consumer;
        $currentBill = $scenario['current_bill'];
        if ($consumer->meter_number === 'MT-7777' && app()->isLocal()) {
            $readings = $consumer->meterReadings()->onlyBillingFixtures()->whereBetween('reading_date', ['2026-10-01', '2026-10-31'])
                ->latest('reading_date')->latest('id')->limit(6)->get();
        } elseif ($currentBill?->isDemoDisconnectionBill()) {
            $readings = $scenario['issued_bills']->pluck('meterReading')->filter()->unique('id')->take(6);
        } else {
            $readings = $consumer->meterReadings()->onlyBillingFixtures()->latest('reading_date')->latest('id')->limit(6)->get();
        }

        return [
            'consumer' => $consumer,
            'billingHistory' => $scenario['bills']->take(6),
            'readingHistory' => $readings,
            'disconnectionHistory' => collect(),
            'billingSummary' => [
                'total_billing' => $scenario['issued_bills']->sum('water_charge'),
                'outstanding_balance' => $scenario['outstanding_balance'],
                'current_bill' => $currentBill,
                'previous_balance_added' => (float) ($currentBill?->arrears_amount ?? 0),
                'previous_billing_count' => max(0, $scenario['issued_bills']->count() - 1),
                'total_consumption' => $readings->sum('consumption'),
            ],
        ];
    }

    private function buildConsumerDashboard(User $user, ConsumerBalanceService $balances): array
    {
        $consumer = $user->consumer;

        if (! $consumer) {
            return [
                'consumer' => null,
                'billingHistory' => collect(),
                'readingHistory' => collect(),
                'disconnectionHistory' => collect(),
                'billingSummary' => [
                    'total_billing' => 0,
                    'outstanding_balance' => 0,
                    'current_bill' => null,
                    'previous_billing_count' => 0,
                    'total_consumption' => 0,
                ],
            ];
        }

        $billingHistory = $this->billQuery()
            ->where('consumer_id', $consumer->id)
            ->latest('billing_period')
            ->latest('id')
            ->limit(6)
            ->get();

        $openBills = $balances->payableOpenBillsFor($consumer->id);

        $currentBill = $this->billQuery()
            ->where('consumer_id', $consumer->id)
            ->latest('billing_period')
            ->latest('id')
            ->first();

        $readingHistory = MeterReading::query()
            ->where('consumer_id', $consumer->id)
            ->latest('reading_date')
            ->latest('id')
            ->limit(6)
            ->get();

        return [
            'consumer' => $consumer,
            'billingHistory' => $billingHistory,
            'readingHistory' => $readingHistory,
            'disconnectionHistory' => app(ConsumerDisconnectionHistoryService::class)->forConsumer($consumer),
            'billingSummary' => [
                'total_billing' => (float) Bill::query()->where('consumer_id', $consumer->id)->where('bill_type', 'Water')->sum('amount'),
                'outstanding_balance' => (float) $openBills->sum(fn (Bill $bill) => $bill->balance_due),
                'current_bill' => $currentBill,
                'previous_balance_added' => (float) ($currentBill?->billing_breakdown['previous_unpaid_balance'] ?? 0),
                'previous_billing_count' => Bill::query()
                    ->where('consumer_id', $consumer->id)
                    ->where('bill_type', 'Water')
                    ->whereDate('billing_period', '<', now()->startOfMonth())
                    ->count(),
                'total_consumption' => (float) MeterReading::query()->where('consumer_id', $consumer->id)->sum('consumption'),
            ],
        ];
    }

    private function buildMeterReaderDashboard(User $user): array
    {
        $readingQuery = MeterReading::query()
            ->with(['consumer', 'bill'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('reader_id', $user->id));

        $readingHistory = (clone $readingQuery)
            ->latest('reading_date')
            ->latest('id')
            ->limit(10)
            ->get();

        return [
            'readingHistory' => $readingHistory,
            'billingHistory' => Bill::query()
                ->with(['consumer', 'meterReading'])
                ->whereHas('meterReading', fn ($query) => $query->where('reader_id', $user->id))
                ->latest('billing_period')
                ->latest('id')
                ->limit(8)
                ->get(),
            'readerSummary' => [
                'total_readings' => (clone $readingQuery)->count(),
                'consumers_read' => (clone $readingQuery)->distinct('consumer_id')->count('consumer_id'),
                'total_consumption' => (float) (clone $readingQuery)->sum('consumption'),
                'ready_for_billing' => (clone $readingQuery)->where('status', 'Encoded')->whereDoesntHave('bill')->count(),
            ],
        ];
    }

    private function billQuery()
    {
        return Bill::query()
            ->where('bill_type', 'Water')
            ->with(['consumer', 'meterReading'])
            ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount');
    }

    private function syncOpenBillStatuses(): void
    {
        Bill::query()
            ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount')
            ->chunkById(100, function ($bills) {
                foreach ($bills as $bill) {
                    $bill->syncPaymentStatus();
                }
            });
    }
}
