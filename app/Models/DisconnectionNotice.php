<?php

namespace App\Models;

use App\Models\Concerns\HasBillingFixtures;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A formal disconnection notice raised from real unpaid bills.
 *
 * The row survives after the notice is settled or the connection is actually
 * disconnected, so the account keeps a complete notice history.
 */
class DisconnectionNotice extends Model
{
    use HasBillingFixtures;

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_SETTLED = 'Settled';

    public const STATUS_DISCONNECTED = 'Disconnected';

    public const STATUS_INCLUDED_IN_DISCONNECTION = 'Included in Disconnection';

    public const SOURCE_WATER = 'water';

    public const SOURCE_LOAN = 'loan';

    protected $fillable = [
        'consumer_id',
        'bill_id',
        'source',
        'loan_id',
        'loan_due_date',
        'loan_balance_due',
        'status',
        'issued_at',
        'deadline_at',
        'settled_at',
        'disconnected_at',
        'confirmed_by',
        'outstanding_amount',
        'outstanding_at_disconnection',
        'disconnection_reason',
        'months_unpaid',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'loan_due_date' => 'date',
            'loan_balance_due' => 'decimal:2',
            'deadline_at' => 'datetime',
            'settled_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'outstanding_amount' => 'decimal:2',
            'outstanding_at_disconnection' => 'decimal:2',
            'months_unpaid' => 'integer',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class)->withBillingFixtures();
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public static function reasonForSources(bool $water, bool $loan): string
    {
        return match (true) {
            $water && $loan => 'Both Water Billing and Meter Loan Overdue',
            $loan => 'Meter Loan Overdue',
            default => 'Water Billing Overdue',
        };
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The consumer has had the full three-day payment window.
     */
    public function paymentWindowHasElapsed(): bool
    {
        return $this->deadline_at !== null
            && now()->greaterThanOrEqualTo($this->deadline_at);
    }
}
