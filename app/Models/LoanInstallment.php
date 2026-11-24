<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanInstallment extends Model
{
    public const STATUSES = ['Upcoming', 'Billed', 'Partially Paid', 'Paid', 'Overdue'];

    protected $fillable = [
        'loan_id',
        'bill_id',
        'installment_number',
        'billing_period',
        'due_date',
        'required_amount',
        'amount_paid',
        'remaining_balance',
        'payment_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'billing_period' => 'date',
            'due_date' => 'date',
            'required_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function repaymentAllocations(): HasMany
    {
        return $this->hasMany(LoanRepaymentAllocation::class);
    }

    public function countsAsBilled(): bool
    {
        return $this->bill_id !== null || in_array($this->status, ['Billed', 'Overdue'], true)
            || (in_array($this->status, ['Paid', 'Partially Paid'], true)
                && ($this->relationLoaded('repaymentAllocations') ? $this->repaymentAllocations->isEmpty() : ! $this->repaymentAllocations()->exists()));
    }

    public function paymentStatusLabel(): string
    {
        return match (true) {
            (float) $this->remaining_balance <= 0 => 'Paid',
            (float) $this->amount_paid > 0 => 'Paid with Balance',
            default => 'Unpaid',
        };
    }

    public function syncStatus(): void
    {
        $required = round((float) $this->required_amount, 2);
        $paid = round((float) $this->amount_paid, 2);
        $remaining = round(max(0, $required - $paid), 2);

        $this->forceFill([
            'remaining_balance' => $remaining,
            'status' => match (true) {
                $remaining <= 0 => 'Paid',
                $paid > 0 => 'Partially Paid',
                $this->due_date && $this->due_date->lt(today()) => 'Overdue',
                default => 'Billed',
            },
        ])->save();
    }
}
