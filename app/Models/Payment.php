<?php

namespace App\Models;

use App\Models\Concerns\HasBillingFixtures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\LoanRepayment;

class Payment extends Model
{
    use HasBillingFixtures;

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (blank($payment->payment_type) && $payment->bill_id) {
                $payment->payment_type = Bill::query()->whereKey($payment->bill_id)->value('bill_type') ?: 'Water';
            }
        });
    }

    protected $appends = [
        'remaining_balance_after_payment',
    ];

    protected $fillable = [
        'bill_id',
        'loan_id',
        'consumer_id',
        'payment_type',
        'submission_key',
        'reference_number',
        'receipt_number',
        'amount',
        'payment_date',
        'payment_method',
        'payment_channel',
        'gateway_reference',
        'status',
        'rejection_reason',
        'verified_at',
        'verified_by',
        'proof_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }

    public function loanRepayment(): HasOne
    {
        return $this->hasOne(LoanRepayment::class);
    }

    /**
     * A rejected submission does not describe the settlement of its bill.
     */
    public function billStatusLabel(): string
    {
        if ($this->is_billing_fixture) {
            return $this->bill?->billingFixtureStatus() ?? 'N/A';
        }

        return $this->status === 'Rejected'
            ? 'N/A'
            : ($this->bill?->paymentSettlementStatus() ?? 'N/A');
    }

    public function getRemainingBalanceAfterPaymentAttribute(): float
    {
        if ($this->payment_type === 'Loan' && $this->loan_id) {
            if ($this->status === 'Verified' && $this->loanRepayment?->loan_balance_after !== null) {
                return (float) $this->loanRepayment->loan_balance_after;
            }

            return $this->loan ? app(\App\Services\LoanRepaymentAllocationService::class)->remainingBalance($this->loan) : 0.0;
        }
        $bill = $this->relationLoaded('bill')
            ? $this->bill
            : $this->bill()->first();

        if (! $bill) {
            return 0.00;
        }

        if ($this->status !== 'Verified') {
            return $bill->balance_due;
        }

        if ($this->payment_type === 'Loan' && $bill->bill_type === 'Loan') {
            $repayment = $this->loanRepayment;
            if ($repayment?->loan_balance_after !== null) {
                return (float) $repayment->loan_balance_after;
            }
            $installment = $bill->loanInstallment()->first();
            if ($installment) {
                $paidThrough = LoanRepayment::query()->where('bill_id', $bill->id)
                    ->where(function ($query): void {
                        $query->whereDate('payment_date', '<', $this->payment_date)
                            ->orWhere(function ($sameDate) {
                                $sameDate->whereDate('payment_date', $this->payment_date)
                                    ->where(function ($sequence): void {
                                        $sequence->whereNull('payment_id')->orWhere('payment_id', '<=', $this->id);
                                    });
                            });
                    })->sum('amount');

                return round(max(0, (float) $installment->required_amount - (float) $paidThrough), 2);
            }
        }

        $paidThroughThisPayment = self::query()
            ->when($this->is_billing_fixture, fn ($query) => $query->onlyBillingFixtures())
            ->where('bill_id', $this->bill_id)
            ->where('payment_type', $bill->bill_type ?: 'Water')
            ->where('status', 'Verified')
            ->where(function ($query) {
                $query
                    ->whereDate('payment_date', '<', $this->payment_date)
                    ->orWhere(function ($sameDateQuery) {
                        $sameDateQuery
                            ->whereDate('payment_date', $this->payment_date)
                            ->where('id', '<=', $this->id);
                    });
            })
            ->sum('amount');

        return round(max(0, (float) $bill->amount - (float) $paidThroughThisPayment - (float) $bill->waived_reconnection_fee_amount), 2);
    }
}
