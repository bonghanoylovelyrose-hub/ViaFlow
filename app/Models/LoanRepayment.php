<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanRepayment extends Model
{
    protected $fillable = [
        'loan_id',
        'bill_id',
        'payment_id',
        'recorded_by_user_id',
        'amount',
        'payment_date',
        'notes',
        'loan_balance_before',
        'loan_balance_after',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'loan_balance_before' => 'decimal:2',
            'loan_balance_after' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function scopeRecordedOrVerified(Builder $query): Builder
    {
        return $query->where(fn ($repayment) => $repayment->whereNull('payment_id')
            ->orWhereHas('payment', fn ($payment) => $payment->where('status', 'Verified')->where('payment_type', 'Loan')));
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LoanRepaymentAllocation::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id')->withTrashed();
    }
}
