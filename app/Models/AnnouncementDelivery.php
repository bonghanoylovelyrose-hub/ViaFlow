<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementDelivery extends Model
{
    protected $fillable = [
        'announcement_id',
        'user_id',
        'recipient_email',
        'status',
        'attempts',
        'queued_at',
        'delivered_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
