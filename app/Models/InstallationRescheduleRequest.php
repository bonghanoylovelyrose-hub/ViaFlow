<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationRescheduleRequest extends Model
{
    public const TYPE_CONNECTION = 'connection';

    public const TYPE_LOAN = 'loan';
    public const TYPE_RECONNECTION = 'reconnection';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'reschedule_type',
        'assignment_number',
        'assigned_at',
        'consumer_reconnection_id',
        'preferred_installation_date',
        'preferred_installation_time',
        'consumer_id',
        'loan_id',
        'status',
        'reason',
        'original_installation_date',
        'original_installation_time',
        'new_installation_date',
        'new_installation_time',
        'admin_notes',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'original_installation_date' => 'date',
            'preferred_installation_date' => 'date',
            'new_installation_date' => 'date',
            'reviewed_at' => 'datetime',
            'assigned_at' => 'datetime',
            'assignment_number' => 'integer',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function reconnection(): BelongsTo
    {
        return $this->belongsTo(ConsumerReconnection::class, 'consumer_reconnection_id');
    }

    public function isReconnection(): bool
    {
        return $this->reschedule_type === self::TYPE_RECONNECTION;
    }

    public function formattedPreferredSchedule(): ?string
    {
        return $this->preferred_installation_date
            ? $this->preferred_installation_date->format('F d, Y').' at '.\Illuminate\Support\Carbon::parse($this->preferred_installation_time)->format('g:i A')
            : null;
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id')->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_ASSIGNED], true);
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isConnection(): bool
    {
        return $this->reschedule_type === self::TYPE_CONNECTION;
    }

    public function isLoan(): bool
    {
        return $this->reschedule_type === self::TYPE_LOAN;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForConsumer(Builder $query, int $consumerId): Builder
    {
        return $query->where('consumer_id', $consumerId);
    }

    public function scopeForLoan(Builder $query, int $loanId): Builder
    {
        return $query->where('loan_id', $loanId);
    }

    public function formattedOriginalSchedule(): string
    {
        $date = $this->original_installation_date?->format('F d, Y') ?? 'Not specified';
        if ($this->original_installation_time) {
            $timeStr = (string) $this->original_installation_time;
            $time = strlen($timeStr) === 5 ? $timeStr . ':00' : $timeStr;
            try {
                $time = \Illuminate\Support\Carbon::createFromFormat('H:i:s', $time)->format('g:i A');
                return "{$date} at {$time}";
            } catch (\Throwable) {
                return "{$date} {$time}";
            }
        }
        return $date;
    }

    public function formattedNewSchedule(): ?string
    {
        if (! $this->new_installation_date) {
            return null;
        }

        $date = $this->new_installation_date->format('F d, Y');
        if ($this->new_installation_time) {
            $timeStr = (string) $this->new_installation_time;
            $time = strlen($timeStr) === 5 ? $timeStr . ':00' : $timeStr;
            try {
                $time = \Illuminate\Support\Carbon::createFromFormat('H:i:s', $time)->format('g:i A');
                return "{$date} at {$time}";
            } catch (\Throwable) {
                return "{$date} {$time}";
            }
        }
        return $date;
    }
}
