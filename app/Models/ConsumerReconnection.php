<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class ConsumerReconnection extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['pending_credentials'];

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function scheduleEmailEvent(): string
    {
        return 'consumer_reconnected_'.$this->id;
    }

    public function scheduledAt(Consumer $consumer): Carbon
    {
        return $consumer->reconnection_scheduled_at ?? $this->installation_scheduled_at;
    }

    public function latestAssignedReschedule(): HasOne
    {
        return $this->hasOne(InstallationRescheduleRequest::class)->ofMany(['id' => 'max'], fn ($query) => $query
            ->whereIn('status', [InstallationRescheduleRequest::STATUS_APPROVED, InstallationRescheduleRequest::STATUS_ASSIGNED])
            ->whereNotNull('new_installation_date')->whereNotNull('new_installation_time'));
    }

    protected function casts(): array
    {
        return [
            'disconnected_at' => 'datetime',
            'reconnection_date' => 'date',
            'installation_scheduled_at' => 'datetime',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'payment_settled_at' => 'datetime',
            'reconnection_fee' => 'decimal:2',
            'verified_payment_ids' => 'array',
            'pending_credentials' => 'encrypted:array',
            'credentials_issued_at' => 'datetime',
        ];
    }
}
