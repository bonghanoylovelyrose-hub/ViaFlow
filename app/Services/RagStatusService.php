<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;

class RagStatusService
{
    public const GREEN = 'green';
    public const AMBER = 'amber';
    public const RED = 'red';

    public function systemHealth(int $pendingAlerts): array
    {
        if ($pendingAlerts >= 15) {
            return ['level' => self::RED, 'label' => 'Needs Attention'];
        }
        if ($pendingAlerts >= 5) {
            return ['level' => self::AMBER, 'label' => 'Moderate'];
        }
        return ['level' => self::GREEN, 'label' => 'Stable'];
    }

    public function consumerStanding(Consumer $consumer): array
    {
        $overdueCount = $consumer->bills()->where('notice_level', ConsumerBalanceService::DISCONNECTION_NOTICE)->count();
        $unpaidCount = $consumer->bills()->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])->count();
        $monthsUnpaid = $consumer->bills()->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])->max('months_unpaid') ?? 0;

        if ($consumer->status === 'Disconnected' || $overdueCount >= 2 || $monthsUnpaid >= 2) {
            return ['level' => self::RED, 'label' => 'Critical'];
        }
        if ($unpaidCount > 0 || $consumer->status === 'For Review') {
            return ['level' => self::AMBER, 'label' => 'Attention'];
        }
        return ['level' => self::GREEN, 'label' => 'Good'];
    }

    public function billStatus(Bill $bill): array
    {
        if ($bill->status === 'Paid' || $bill->balance_due <= 0) {
            return ['level' => self::GREEN, 'label' => 'Paid'];
        }

        if ($bill->due_date->isBefore(today())) {
            return ['level' => self::RED, 'label' => 'Overdue'];
        }

        return ['level' => self::AMBER, 'label' => 'Due'];
    }

    public function loanHealth(Loan $loan): array
    {
        if ($loan->status === 'Rejected') {
            return ['level' => self::RED, 'label' => 'Rejected'];
        }
        if ($loan->status === 'Completed') {
            return ['level' => self::GREEN, 'label' => 'Completed'];
        }
        if ($loan->status === 'Pending Review') {
            return ['level' => self::AMBER, 'label' => 'Pending'];
        }
        if ($loan->status === 'Approved' && $loan->next_due_date) {
            if ($loan->next_due_date->isPast()) {
                return ['level' => self::RED, 'label' => 'Overdue'];
            }
            if ($loan->next_due_date->diffInDays(now()) <= 3) {
                return ['level' => self::AMBER, 'label' => 'Due Soon'];
            }
            return ['level' => self::GREEN, 'label' => 'On Track'];
        }
        if ($loan->risk_level === 'High Risk') {
            return ['level' => self::RED, 'label' => 'High Risk'];
        }
        if ($loan->risk_level === 'Medium Risk') {
            return ['level' => self::AMBER, 'label' => 'Medium Risk'];
        }
        return ['level' => self::GREEN, 'label' => 'Low Risk'];
    }

    public function dashboardStat(string $metric, int $value): array
    {
        return match ($metric) {
            'unpaid_bills' => $value >= 50 ? ['level' => self::RED, 'label' => 'High']
                : ($value >= 10 ? ['level' => self::AMBER, 'label' => 'Moderate']
                : ['level' => self::GREEN, 'label' => 'Low']),
            'pending_payments' => $value >= 20 ? ['level' => self::RED, 'label' => 'High']
                : ($value >= 5 ? ['level' => self::AMBER, 'label' => 'Moderate']
                : ['level' => self::GREEN, 'label' => 'Low']),
            'flagged_accounts' => $value >= 10 ? ['level' => self::RED, 'label' => 'Critical']
                : ($value >= 1 ? ['level' => self::AMBER, 'label' => 'Warning']
                : ['level' => self::GREEN, 'label' => 'Clear']),
            default => ['level' => self::GREEN, 'label' => 'Normal'],
        };
    }
}
