<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'category',
        'audience',
        'is_pinned',
        'published_at',
        'recipient_user_ids',
        'photo_path',
        'priority', 'expires_at', 'affected_area', 'service_starts_at', 'service_restores_at', 'notifications_queued_at', 'edited_at', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'service_starts_at' => 'datetime',
            'service_restores_at' => 'datetime',
            'notifications_queued_at' => 'datetime',
            'edited_at' => 'datetime',
            'recipient_user_ids' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('published_at')->orWhere('published_at', '<=', now());
        })->where(function (Builder $q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isActive(): bool
    {
        return (! $this->published_at || $this->published_at->lte(now()))
            && (! $this->expires_at || $this->expires_at->gt(now()));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    public function recipientUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_recipients')->withTimestamps();
    }

    public function deliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AnnouncementDelivery::class);
    }
}
