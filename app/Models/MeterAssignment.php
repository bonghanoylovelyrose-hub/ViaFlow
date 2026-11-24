<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterAssignment extends Model
{
    protected $fillable = [
        'consumer_id',
        'meter_number',
        'assigned_at',
        'retired_at',
        'reason',
        'replaced_by_assignment_id',
        'assigned_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id')->withTrashed();
    }

    public function isActive(): bool
    {
        return $this->retired_at === null;
    }
}