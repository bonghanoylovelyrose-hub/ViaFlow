<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    public const TYPE_NEW_CONNECTION = 'New Connection';

    public const TYPE_RECONNECTION = 'Reconnection';

    public const STATUS_PENDING = 'Pending';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUS_COMPLETED = 'Completed';

    protected $fillable = [
        'consumer_id',
        'request_type',
        'status',
        'notes',
        'information',
        'proof_path',
        'admin_notes',
        'current_balance_snapshot',
        'requested_at',
        'reviewed_at',
        'reviewed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'current_balance_snapshot' => 'decimal:2',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id')->withTrashed();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}