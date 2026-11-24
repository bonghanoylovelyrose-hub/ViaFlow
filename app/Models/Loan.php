<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Loan extends Model
{
    protected static function booted(): void
    {
        static::updating(function (Loan $loan): void {
            if ($loan->isDirty('status')
                && in_array($loan->status, ['Approved', 'Rejected'], true)
                && $loan->decision_at === null) {
                $loan->decision_at = now();
            }
        });
    }

    public const APPLICATION_APPROVED_STATUSES = ['Ready for Scheduling', 'Approved', 'Completed', 'Active', 'Ongoing'];
    public const APPLICATION_REJECTED_STATUSES = ['Rejected'];
    public const PAYMENT_STATUSES = ['Ready for Scheduling', 'Approved', 'Active', 'Ongoing'];

    public const PENDING_STATUSES = ['Pending Review', 'Needs Review'];
    public const ACTIVE_STATUSES = ['Pending Review', 'Needs Review', 'Ready for Scheduling', 'Approved', 'Active', 'Ongoing'];
    public const REVIEW_STATUSES = ['Pending Review', 'Reviewed', 'Installation Scheduled', 'Active', 'Completed'];
    public const INSTALLATION_STATUSES = ['Not Scheduled', 'Scheduled', 'Completed'];

    public const INSTALLATION_TIMEZONE = 'Asia/Manila';

    protected $appends = [
        'total_repaid',
        'balance_remaining',
        'installments_billed',
        'remaining_installments',
        'amount_billed',
        'unpaid_loan_balance',
    ];

    protected $fillable = [
        'consumer_id',
        'applied_by_user_id',
        'amount',
        'monthly_income',
        'income_verified_monthly_amount',
        'proof_of_income_path',
        'income_ocr_data',
        'income_verification_status',
        'income_verified_by',
        'income_verified_at',
        'income_verification_notes',
        'income_rejection_reason',
        'review_requested_at',
        'review_request_reason',
        'term_months',
        'purpose',
        'terms_agreed_at',
        'submission_confirmed_at',
        'terms_version',
        'terms_text',
        'material_meter_cost',
        'material_hose_cost',
        'material_other_cost',
        'installation_cost_total',
        'installment_schedule_snapshot',
        'status',
        'review_status',
        'risk_level',
        'recommendation_notes',
        'review_notes',
        'installation_status',
        'installation_date',
        'installation_time',
        'installation_notes',
        'monthly_installment',
        'applied_at',
        'approved_at',
        'released_at',
        'decision_at',
        'next_due_date',
        'start_billing_period',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'monthly_income' => 'decimal:2',
            'income_verified_monthly_amount' => 'decimal:2',
            'income_ocr_data' => 'array',
            'income_verified_at' => 'datetime',
            'review_requested_at' => 'datetime',
            'material_meter_cost' => 'decimal:2',
            'material_hose_cost' => 'decimal:2',
            'material_other_cost' => 'decimal:2',
            'installation_cost_total' => 'decimal:2',
            'installment_schedule_snapshot' => 'array',
            'monthly_installment' => 'decimal:2',
            'terms_agreed_at' => 'datetime',
            'submission_confirmed_at' => 'datetime',
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
            'released_at' => 'datetime',
            'decision_at' => 'datetime',
            'installation_date' => 'date',
            'installation_completed_at' => 'datetime',
            'next_due_date' => 'date',
            'start_billing_period' => 'date',
        ];
    }

    public function applicationStatusCategory(): string
    {
        if (in_array($this->status, self::APPLICATION_APPROVED_STATUSES, true)) return 'Approved';
        if (in_array($this->status, self::APPLICATION_REJECTED_STATUSES, true)) return 'Rejected';

        return 'Pending Review';
    }

    public function scopeWithExpiredUnpaidSchedule(Builder $query): Builder
    {
        return $query->whereIn('loans.status', self::PAYMENT_STATUSES)
            ->whereHas('installments', fn (Builder $rows) => $rows
                ->whereColumn('loan_installments.installment_number', 'loans.term_months')
                ->whereDate('loan_installments.due_date', '<', today(self::INSTALLATION_TIMEZONE)->toDateString()))
            ->whereHas('installments', fn (Builder $rows) => $rows->where('remaining_balance', '>', 0));
    }

    public function scopeApplicationCategory(Builder $query, string $category): Builder
    {
        return match ($category) {
            'Approved' => $query->whereIn('status', self::APPLICATION_APPROVED_STATUSES),
            'Rejected' => $query->whereIn('status', self::APPLICATION_REJECTED_STATUSES),
            'Pending Review' => $query->where(fn (Builder $q) => $q->whereNull('status')->orWhereNotIn('status', array_merge(self::APPLICATION_APPROVED_STATUSES, self::APPLICATION_REJECTED_STATUSES))),
            default => $query,
        };
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function installationCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installation_completed_by')->withTrashed();
    }

    public function installationCompletionDate(): ?Carbon
    {
        return $this->installation_status === 'Completed'
            ? ($this->installation_completed_at ?? $this->released_at)
            : null;
    }

    public function installationSchedule(): ?Carbon
    {
        if (! $this->installation_date || ! $this->installation_time) {
            return null;
        }

        return Carbon::parse($this->installation_date->toDateString().' '.$this->installation_time, self::INSTALLATION_TIMEZONE);
    }

    public function isApprovedForInstallation(): bool
    {
        $recommendation = $this->latestDssEvaluation?->recommendation;

        return $recommendation === 'Approved'
            || ($recommendation === null && $this->applicationStatusCategory() === 'Approved');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id')->withTrashed();
    }

    public function incomeVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'income_verified_by')->withTrashed();
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    public function installationRescheduleRequests(): HasMany
    {
        return $this->hasMany(InstallationRescheduleRequest::class);
    }

    public function pendingInstallationRescheduleRequest(): ?InstallationRescheduleRequest
    {
        return $this->installationRescheduleRequests()
            ->where('status', InstallationRescheduleRequest::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    public function agreement(): HasOne
    {
        return $this->hasOne(LoanAgreement::class);
    }

    public function initialInstallationEmailEvent(): string
    {
        return 'loan_installation_'.$this->id.'_initial';
    }

    public function latestDssEvaluation(): HasOne
    {
        return $this->hasOne(LoanDssEvaluation::class)->latestOfMany('evaluated_at');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function getTotalRepaidAttribute(): float
    {
        if ($this->hasInstallmentRows()) {
            if (array_key_exists('installments_sum_amount_paid', $this->attributes)) {
                return round((float) $this->attributes['installments_sum_amount_paid'], 2);
            }

            if ($this->relationLoaded('installments')) {
                return round((float) $this->installments->sum('amount_paid'), 2);
            }

            return round((float) $this->installments()->sum('amount_paid'), 2);
        }

        return $this->legacyTotalRepaid();
    }

    public function getBalanceRemainingAttribute(): float
    {
        return round(max(0, (float) $this->amount - $this->total_repaid), 2);
    }

    public function getInstallmentsBilledAttribute(): int
    {
        if ($this->hasInstallmentRows()) {
            if ($this->relationLoaded('installments')) {
                return $this->installments->filter(fn (LoanInstallment $item) => $item->countsAsBilled())->count();
            }

            return $this->installments()->where(fn ($query) => $query->whereNotNull('bill_id')->orWhereIn('status', ['Billed', 'Overdue'])->orWhere(fn ($legacy) => $legacy->whereIn('status', ['Paid', 'Partially Paid'])->whereDoesntHave('repaymentAllocations')))->count();
        }

        return $this->legacyInstallmentsBilled();
    }

    public function getRemainingInstallmentsAttribute(): int
    {
        return max(0, (int) $this->term_months - $this->installments_billed);
    }

    public function getAmountBilledAttribute(): float
    {
        if ($this->hasInstallmentRows()) {
            if ($this->relationLoaded('installments')) {
                return round((float) $this->installments->filter(fn (LoanInstallment $item) => $item->countsAsBilled())->sum('required_amount'), 2);
            }

            return round((float) $this->installments()->where(fn ($query) => $query->whereNotNull('bill_id')->orWhereIn('status', ['Billed', 'Overdue'])->orWhere(fn ($legacy) => $legacy->whereIn('status', ['Paid', 'Partially Paid'])->whereDoesntHave('repaymentAllocations')))->sum('required_amount'), 2);
        }

        return $this->legacyAmountBilled();
    }

    public function getUnpaidLoanBalanceAttribute(): float
    {
        return $this->balance_remaining;
    }

    public function regularMonthlyInstallment(): float
    {
        return round((float) $this->monthly_installment, 2);
    }

    public function getRepaymentStatusAttribute(): string
    {
        if ($this->status === 'Completed' || $this->balance_remaining <= 0) {
            return 'Completed';
        }

        if ($this->amount_billed <= 0) {
            return 'Not Started';
        }

        if ($this->total_repaid > 0) {
            return 'Partially Paid';
        }

        return 'Unpaid';
    }

    public function installmentAmountForNumber(int $installmentNumber): float
    {
        $termMonths = (int) $this->term_months;

        if ($installmentNumber < 1 || $installmentNumber > $termMonths || $termMonths <= 0) {
            return 0.0;
        }

        $regularInstallment = $this->regularMonthlyInstallment();

        if ($installmentNumber === $termMonths) {
            return round(max(0, (float) $this->amount - ($regularInstallment * ($termMonths - 1))), 2);
        }

        return $regularInstallment;
    }

    public function scopeBlockingNewApplication(Builder $query): Builder
    {
        return $query->where(function (Builder $loanQuery): void {
            $loanQuery
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->orWhere(function (Builder $unpaidQuery): void {
                    $unpaidQuery
                        ->where('status', '!=', 'Rejected')
                        ->whereRaw('amount > COALESCE((select sum(amount) from loan_repayments where loan_repayments.loan_id = loans.id), 0)');
                });
        });
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query
            ->whereIn('status', self::PENDING_STATUSES)
            ->whereNotNull('applied_at');
    }

    public function blocksNewApplication(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true)
            || ($this->status !== 'Rejected' && $this->balance_remaining > 0);
    }

    public function syncRepaymentStatus(): void
    {
        $updates = [];

        $hasUnpaidInstallments = $this->hasInstallmentRows()
            && $this->installments()->where('remaining_balance', '>', 0)->exists();

        if ($this->installments_billed >= (int) $this->term_months && $this->balance_remaining <= 0 && ! $hasUnpaidInstallments) {
            $updates['status'] = 'Completed';
            $updates['next_due_date'] = null;
        } elseif (in_array($this->status, ['Approved', 'Active', 'Ongoing'], true)) {
            $updates['status'] = 'Approved';
            $updates['next_due_date'] = $this->next_due_date
                ? $this->next_due_date->copy()->addMonth()->toDateString()
                : now()->addMonth()->toDateString();
        }

        if ($updates !== []) {
            $this->update($updates);
        }
    }

    private function hasInstallmentRows(): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('loan_installments')) {
            return false;
        }

        if ($this->relationLoaded('installments')) {
            return $this->installments->isNotEmpty();
        }

        return $this->installments()->exists();
    }

    private function legacyTotalRepaid(): float
    {
        if (array_key_exists('repayments_sum_amount', $this->attributes)) {
            return round((float) $this->attributes['repayments_sum_amount'], 2);
        }

        if ($this->relationLoaded('repayments')) {
            return round((float) $this->repayments->sum('amount'), 2);
        }

        return round((float) $this->repayments()->sum('amount'), 2);
    }

    private function legacyInstallmentsBilled(): int
    {
        if (array_key_exists('bills_count', $this->attributes)) {
            return (int) $this->attributes['bills_count'];
        }

        if ($this->relationLoaded('bills')) {
            return $this->bills->where('loan_installment_amount', '>', 0)->count();
        }

        return $this->bills()->where('loan_installment_amount', '>', 0)->count();
    }

    private function legacyAmountBilled(): float
    {
        if (array_key_exists('bills_sum_loan_installment_amount', $this->attributes)) {
            return round((float) $this->attributes['bills_sum_loan_installment_amount'], 2);
        }

        if ($this->relationLoaded('bills')) {
            return round((float) $this->bills->sum('loan_installment_amount'), 2);
        }

        return round((float) $this->bills()->sum('loan_installment_amount'), 2);
    }
}
