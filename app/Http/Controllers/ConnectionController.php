<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\ServiceRequest;
use App\Services\ConsumerBalanceService;
use App\Services\ConsumerBillingScenario;
use App\Services\DisconnectionNoticeService;
use App\Services\MeterAssignmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConnectionController extends Controller
{
    public function status(Request $request, ConsumerBillingScenario $scenario): View
    {
        $user = $request->user();
        $consumer = $user->consumer;
        $hasBillingScenario = $scenario->isAvailableFor($consumer);
        $billingScenario = $hasBillingScenario && $request->query('ledger') !== 'recorded' ? $scenario->snapshot($consumer) : null;

        // Keep this page's notice, months-unpaid and balance in step with the
        // consumer's real billing and payment records.
        if (! $billingScenario) {
            $consumer?->syncAccountStanding();
        }

        $balances = app(ConsumerBalanceService::class);
        $meters = app(MeterAssignmentService::class);

        $outstandingBalance = $consumer ? $balances->totalOutstandingFor($consumer->id) : 0.0;
        $monthsUnpaid = $consumer ? $balances->consecutiveUnpaidMonthsFor($consumer->id) : 0;
        $graceDays = ConsumerBalanceService::PAYMENT_GRACE_DAYS;

        $activeRequest = $consumer
            ? $consumer->serviceRequests()
                ->where('status', ServiceRequest::STATUS_PENDING)
                ->orderByDesc('requested_at')
                ->first()
            : null;

        $reconnectionHistory = $consumer
            ? $consumer->serviceRequests()
                ->where('request_type', ServiceRequest::TYPE_RECONNECTION)
                ->orderByDesc('requested_at')
                ->get()
            : collect();

        $activeDisconnectionNotice = $consumer ? app(DisconnectionNoticeService::class)
            ->activeNoticeFor($consumer->id, DisconnectionNotice::SOURCE_WATER) : null;
        $hasActiveDisconnectionNotice = $activeDisconnectionNotice !== null
            || ($consumer && $balances->hasWaterDisconnectionNotice($consumer->id));

        // Paying alone is not enough: an administrator must verify and approve
        // the payment first, so the button stays disabled until then.
        $paymentVerified = filled($consumer?->full_payment_verified_at);
        $latestReconnection = $consumer?->latestReconnection;

        $canRequestReconnection = $consumer
            && in_array($consumer->status, Consumer::DISCONNECTION_ELIGIBLE_STATUSES, true)
            && $outstandingBalance <= 0
            && app(DisconnectionNoticeService::class)->outstandingForReconnection($consumer) <= 0
            && $paymentVerified
            && $activeRequest === null;

        return view('consumer.connection.index', [
            'consumer' => $consumer,
            'hasBillingScenario' => $hasBillingScenario,
            'billingScenario' => $billingScenario,
            'outstandingBalance' => $billingScenario['outstanding_balance'] ?? $outstandingBalance,
            'monthsUnpaid' => $monthsUnpaid,
            'monthsRequired' => ConsumerBalanceService::DISCONNECTION_NOTICE_AFTER_MONTHS,
            'graceDays' => $graceDays,
            'hasActiveDisconnectionNotice' => $billingScenario ? $billingScenario['notice'] !== null : $hasActiveDisconnectionNotice,
            'activeDisconnectionNotice' => $activeDisconnectionNotice,
            'paymentVerified' => $paymentVerified,
            'fullPaymentVerifiedAt' => $consumer?->full_payment_verified_at,
            'activeRequest' => $activeRequest,
            'reconnectionHistory' => $reconnectionHistory,
            'canRequestReconnection' => ! $billingScenario && $canRequestReconnection,
            'latestReconnection' => $latestReconnection,
            'reconnectionRescheduleUsage' => $consumer?->reconnection_pending && $latestReconnection
                ? app(\App\Services\InstallationRescheduleService::class)->usage($consumer, $latestReconnection)
                : null,
            'meterHistory' => $consumer ? $meters->historyFor($consumer) : collect(),
        ]);
    }
}
