<?php

namespace App\Models;

use App\Models\Concerns\HasBillingFixtures;
use App\Services\BillingComputationService;
use App\Services\ConsumerBalanceService;
use App\Services\WaterBillingLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use HasBillingFixtures;

    public const DEMO_DISCONNECTION_PRN_PREFIX = 'DEMO-DISC-';

    protected static function booted(): void
    {
        static::creating(function (Bill $bill): void {
            $bill->bill_type = $bill->bill_type ?: 'Water';
        });
    }

    protected $appends = [
        'verified_paid_amount',
        'balance_due',
        'billing_breakdown',
    ];

    protected $fillable = [
        'consumer_id',
        'bill_type',
        'meter_reading_id',
        'loan_id',
        'billing_period',
        'due_date',
        'prn',
        'amount',
        'base_charge',
        'water_charge',
        'arrears_amount',
        'penalty_amount',
        'carried_forward_from_bill_id',
        'carried_forward_to_bill_id',
        'loan_installment_amount',
        'units_consumed',
        'unit_rate',
        'status',
        'months_unpaid',
        'notice_level',
        'request_type',
        'request_status',
    ];

    protected function casts(): array
    {
        return [
            'billing_period' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'base_charge' => 'decimal:2',
            'water_charge' => 'decimal:2',
            'arrears_amount' => 'decimal:2',
            'carried_balance_snapshot' => 'decimal:2',
            'carried_paid_snapshot' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'loan_installment_amount' => 'decimal:2',
            'waived_reconnection_fee_amount' => 'decimal:2',
            'units_consumed' => 'decimal:2',
            'unit_rate' => 'decimal:2',
            'is_demo' => 'boolean',
        ];
    }

    public function scopeForCollection(Builder $query): Builder
    {
        return $query->where('bills.is_demo', false)->whereNull('bills.superseded_by_bill_id');
    }

    public function isDemoDisconnectionBill(): bool
    {
        return str_starts_with((string) $this->prn, self::DEMO_DISCONNECTION_PRN_PREFIX);
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function meterReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class)->withBillingFixtures();
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->withBillingFixtures();
    }

    public function loanInstallments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    public function loanInstallment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LoanInstallment::class);
    }

    public function carriedForwardTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_forward_to_bill_id')->withBillingFixtures();
    }

    public function carriedForwardFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_forward_from_bill_id')->withBillingFixtures();
    }

    public function earlierIssues(): HasMany
    {
        return $this->hasMany(self::class, 'superseded_by_bill_id')->withBillingFixtures();
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_bill_id')->withBillingFixtures();
    }

    /**
     * True when this bill's outstanding balance was moved into a later bill.
     *
     * A carried balance is no longer owed here, so it must never be counted a
     * second time alongside the newer bill that absorbed it.
     */
    public function balanceIsCarriedForward(): bool
    {
        return filled($this->carried_forward_to_bill_id);
    }

    public function balanceHasBeenCarriedForward(): bool
    {
        return $this->balanceIsCarriedForward() && (! $this->is_billing_fixture || ($this->carriedForwardTo?->hasBeenIssued() ?? true));
    }

    public function carryBalanceInto(Bill $bill): void
    {
        $this->forceFill([
            'carried_forward_to_bill_id' => $bill->id,
            'carried_balance_snapshot' => $this->balance_due,
            'carried_paid_snapshot' => $this->verified_paid_amount,
            'months_unpaid' => 0,
            'notice_level' => null,
            'status' => 'Paid',
        ])->save();
        $this->setRelation('carriedForwardTo', $bill);
    }

    public function isOutstanding(): bool
    {
        return $this->balance_due > 0 && ! $this->balanceHasBeenCarriedForward();
    }

    public function hasBeenIssued(): bool
    {
        return ! $this->isNovemberProjection() && ($this->created_at === null || $this->created_at->lessThanOrEqualTo(now()));
    }

    public function isNovemberProjection(): bool
    {
        return $this->is_billing_fixture && $this->bill_type === 'Water'
            && $this->billing_period?->toDateString() === WaterBillingLedger::NOVEMBER_PERIOD
            && WaterBillingLedger::isScenarioAccount($this->consumer);
    }

    public function billingFixtureStatus(): string
    {
        if (! $this->hasBeenIssued()) {
            return 'Scheduled';
        }

        if ($this->balanceHasBeenCarriedForward()) {
            return $this->statement_paid_amount > 0 ? 'Partially Paid · Carried Forward' : 'Unpaid · Carried Forward';
        }

        $status = $this->computedStatus();

        return $status === 'Paid with Balance' ? 'Partially Paid' : $status;
    }

    public function waterBillingStatus(): string
    {
        if ($this->is_billing_fixture || ($this->balanceHasBeenCarriedForward() && WaterBillingLedger::isScenarioAccount($this->consumer))) {
            return $this->billingFixtureStatus();
        }

        return $this->computedStatus();
    }

    public function getEarlierIssueCreditAttribute(): float
    {
        if (! $this->is_billing_fixture && ! $this->relationLoaded('earlierIssues')
            && ($this->bill_type !== 'Water' || $this->billing_period?->toDateString() !== WaterBillingLedger::NOVEMBER_PERIOD
                || ! WaterBillingLedger::isScenarioAccount($this->consumer))) {
            return 0.0;
        }

        return round((float) $this->earlierIssues->sum('verified_paid_amount'), 2);
    }

    public function unsettledAmount(): float
    {
        return round(max(0, (float) $this->amount - $this->verified_paid_amount
            - $this->earlier_issue_credit - (float) $this->waived_reconnection_fee_amount), 2);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (app()->isLocal() && request()->routeIs('billing.show', 'billing.statement.pdf')) {
            return $this->resolveRouteBindingQuery($this->newQuery()->withBillingFixtures(), $value, $field)->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public function getVerifiedPaidAmountAttribute(): float
    {
        if (($this->bill_type ?? 'Water') === 'Loan') {
            $installment = $this->relationLoaded('loanInstallment')
                ? $this->loanInstallment
                : $this->loanInstallment()->first();
            if ($installment) {
                return round((float) $installment->amount_paid, 2);
            }
        }

        if (array_key_exists('verified_payments_sum_amount', $this->attributes)) {
            return round((float) $this->attributes['verified_payments_sum_amount'], 2);
        }

        if ($this->relationLoaded('payments')) {
            return round((float) $this->payments
                ->where('status', 'Verified')
                ->where('payment_type', $this->bill_type ?: 'Water')
                ->sum('amount'), 2);
        }

        return round((float) $this->payments()
            ->where('status', 'Verified')
            ->where('payment_type', $this->bill_type ?: 'Water')
            ->sum('amount'), 2);
    }

    public function getBalanceDueAttribute(): float
    {
        if (($this->bill_type ?? 'Water') === 'Loan') {
            $installment = $this->relationLoaded('loanInstallment')
                ? $this->loanInstallment
                : $this->loanInstallment()->first();
            if ($installment) {
                return round(max(0, (float) $installment->remaining_balance), 2);
            }
        }

        // A balance that was carried into a later bill is no longer owed here.
        // It is reported on the bill that now holds it, so it is never shown as
        // outstanding on two bills at once.
        if ($this->superseded_by_bill_id || ($this->is_billing_fixture && ! $this->hasBeenIssued()) || $this->balanceHasBeenCarriedForward()) {
            return 0.00;
        }

        return $this->unsettledAmount();
    }

    /**
     * A historical snapshot is distinct from the live payable balance, which
     * becomes zero on a source bill after its debt moves to a later bill.
     * Null means a legacy carryover cannot be reconstructed reliably.
     */
    public function getStatementBalanceAttribute(): ?float
    {
        if ($this->balanceHasBeenCarriedForward()) {
            return $this->carried_balance_snapshot === null ? null : (float) $this->carried_balance_snapshot;
        }

        return $this->balance_due;
    }

    public function getStatementPaidAmountAttribute(): ?float
    {
        if ($this->balanceHasBeenCarriedForward()) {
            return $this->carried_paid_snapshot === null ? null : (float) $this->carried_paid_snapshot;
        }

        return $this->verified_paid_amount;
    }

    public function getBillingBreakdownAttribute(): array
    {
        $standardUnitRate = BillingComputationService::NON_MEMBER_UNIT_RATE;
        $unitsConsumed = (float) ($this->units_consumed ?? $this->meterReading?->consumption ?? 0);
        $unitRate = (float) ($this->unit_rate ?? $standardUnitRate);
        $baseCharge = (float) ($this->base_charge ?? 0);
        $waterCharge = (float) ($this->water_charge ?? 0);
        $arrearsAmount = (float) ($this->arrears_amount ?? 0);
        $penaltyAmount = (float) ($this->penalty_amount ?? 0);
        $loanInstallmentAmount = (float) ($this->loan_installment_amount ?? 0);
        $grossUsageCharge = round($unitsConsumed * $standardUnitRate, 2);
        $actualUsageCharge = round($unitsConsumed * $unitRate, 2);
        $calculatedCurrentCharges = round($baseCharge + $actualUsageCharge, 2);
        $hasVerifiedMembership = $this->consumer?->hasVerifiedMembership() ?? false;
        $discountAmount = $hasVerifiedMembership
            ? round(max(0, $grossUsageCharge - $actualUsageCharge), 2)
            : 0.00;
        $currentBillAmount = $calculatedCurrentCharges;

        if ($waterCharge > 0) {
            $currentBillAmount = $waterCharge;
        }

        if ($currentBillAmount <= 0 && (float) $this->amount > 0) {
            $currentBillAmount = max(0, (float) $this->amount - $arrearsAmount - $penaltyAmount - $loanInstallmentAmount);
        }

        return [
            'consumer_type' => $hasVerifiedMembership ? 'Member' : 'Non-Member',
            'previous_bill_amount' => $arrearsAmount,
            'previous_unpaid_balance' => $arrearsAmount,
            'previous_meter_reading' => (float) ($this->meterReading?->previous_reading ?? 0),
            'current_meter_reading' => (float) ($this->meterReading?->current_reading ?? 0),
            'total_consumption' => $unitsConsumed,
            'standard_unit_rate' => $standardUnitRate,
            'rate_per_cubic_meter' => $unitRate,
            'base_charge' => $baseCharge,
            'usage_charge' => $actualUsageCharge,
            'gross_usage_charge' => $grossUsageCharge,
            'calculated_current_charges' => $calculatedCurrentCharges,
            'current_bill_amount' => round($currentBillAmount, 2),
            'current_bill_formula' => 'Base charge + usage charge',
            'penalties' => $penaltyAmount,
            'loan_installment_amount' => $loanInstallmentAmount,
            'discounts' => $discountAmount,
            'total_before_payments' => (float) $this->amount,
            'total_amount_due' => (float) $this->amount,
            'verified_payments' => $this->verified_paid_amount,
            'remaining_balance' => $this->balance_due,
        ];
    }

    public function getPreviousBalanceAuditAttribute(): ?array
    {
        $carriedBalance = round((float) ($this->arrears_amount ?? 0), 2);

        if ($carriedBalance <= 0 || ! $this->billing_period || ! $this->consumer_id) {
            return null;
        }

        $sourceBill = $this->carriedForwardFrom ?? self::query()
            ->when($this->is_billing_fixture, fn (Builder $query) => $query->onlyBillingFixtures())
            ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount')
            ->where('consumer_id', $this->consumer_id)
            ->where('bill_type', 'Water')
            ->where('billing_period', '<', $this->billing_period->toDateString())
            ->orderByDesc('billing_period')
            ->orderByDesc('id')
            ->first();

        $sourceAmount = round((float) ($sourceBill?->amount ?? $carriedBalance), 2);
        $verifiedPayments = $sourceBill?->statement_paid_amount;
        $paymentsAppliedBeforeCarryover = round(max(0, $sourceAmount - $carriedBalance), 2);

        return [
            'has_previous_balance' => true,
            'consumer_name' => $this->consumer?->full_name ?? 'This consumer',
            'source_bill_id' => $sourceBill?->id,
            'source_prn' => $sourceBill?->prn,
            'source_period' => $sourceBill?->billing_period,
            'source_bill_amount' => $sourceAmount,
            'verified_payments' => $verifiedPayments,
            'payments_applied_before_carryover' => $paymentsAppliedBeforeCarryover,
            'remaining_balance' => $carriedBalance,
            'source_bill_current_balance' => round((float) ($sourceBill?->balance_due ?? $carriedBalance), 2),
            'carried_balance' => $carriedBalance,
            'current_period' => $this->billing_period,
            'reason' => 'The previous bill still had an unpaid balance when this bill was generated, so the remaining amount was automatically carried forward into the current billing period.',
        ];
    }

    /**
     * Settlement status is independent of the status of any single payment.
     * Loan installments include recorded repayments in their existing ledger.
     */
    public function paymentSettlementStatus(): string
    {
        return match (true) {
            $this->superseded_by_bill_id && $this->unsettledAmount() > 0 => 'Superseded',
            $this->is_billing_fixture && ! $this->hasBeenIssued() => 'Scheduled',
            $this->balanceHasBeenCarriedForward() || $this->balance_due <= 0 => 'Paid',
            $this->verified_paid_amount > 0 || $this->earlier_issue_credit > 0 => 'Paid with Balance',
            default => 'Unpaid',
        };
    }

    /**
     * Retain overdue tracking separately from payment settlement labels.
     */
    public function computedStatus(): string
    {
        $settlementStatus = $this->paymentSettlementStatus();

        return $settlementStatus === 'Unpaid' && $this->hasBeenIssued() && $this->due_date?->isPast()
            ? 'Overdue'
            : $settlementStatus;
    }

    public function syncPaymentStatus(bool $syncConsumer = true): void
    {
        if ($this->superseded_by_bill_id || ($this->is_billing_fixture && ! $this->hasBeenIssued())) {
            return;
        }
        // A balance that has moved into a later bill is settled by definition.
        if ($this->balanceHasBeenCarriedForward()) {
            $this->forceFill([
                'status' => 'Paid',
                'months_unpaid' => 0,
                'notice_level' => null,
            ])->save();

            return;
        }

        $balanceDue = $this->balance_due;
        $status = $this->computedStatus();

        $this->forceFill([
            'status' => $status,
            'months_unpaid' => $balanceDue <= 0 ? 0 : $this->months_unpaid,
        ])->save();

        if ($balanceDue <= 0) {
            $this->forceFill(['notice_level' => null])->save();
        }

        if ($syncConsumer) {
            $this->consumer()->first()?->syncAccountStanding();
        }
    }
}
