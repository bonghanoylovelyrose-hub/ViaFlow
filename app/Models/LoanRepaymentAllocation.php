<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepaymentAllocation extends Model
{
    protected $fillable = ['loan_repayment_id', 'loan_installment_id', 'amount', 'installment_balance_after'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'installment_balance_after' => 'decimal:2'];
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'loan_installment_id');
    }
}
