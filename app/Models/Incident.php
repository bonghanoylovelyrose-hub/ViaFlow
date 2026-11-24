<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    private const REPORT_TIMEZONE = 'Asia/Manila';

    public const STATUS_PENDING = 'Pending';

    public const STATUS_RESOLVED = 'Resolved';

    public const OPEN_STATUSES = ['Pending', 'Ongoing', 'For Consumer Confirmation'];

    public const CATEGORIES = ['Water Leak', 'No Water Supply', 'Water Quality Issue', 'Meter Concern', 'Others'];

    public const PRIORITY_URGENT = 'Urgent';

    public const PRIORITY_NON_URGENT = 'Non-Urgent';

    public const PRIORITY_NEEDS_REVIEW = 'Needs Review';

    public const PRIORITIES = [self::PRIORITY_URGENT, self::PRIORITY_NON_URGENT, self::PRIORITY_NEEDS_REVIEW];

    public const PRIORITY_ORDER = [self::PRIORITY_URGENT, self::PRIORITY_NEEDS_REVIEW, self::PRIORITY_NON_URGENT];

    public const PRIORITY_REVIEW_LOCKED_MESSAGE = 'This incident has been resolved. Priority classification can no longer be modified.';

    protected $fillable = [
        'consumer_id',
        'reported_by',
        'category',
        'custom_incident_type',
        'location',
        'purok',
        'block',
        'description',
        'priority',
        'automatic_priority',
        'classification_reason',
        'status',
        'reported_at',
        'resolution_notes',
        'photo_path',
        'media_type',
        'confirmed_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'priority_reviewed_at' => 'datetime',
        ];
    }

    public function formatSubmittedAt(string $format): ?string
    {
        // Older databases could rewrite reported_at when a report was edited.
        $submittedAt = $this->created_at ?? $this->reported_at;

        return $submittedAt?->copy()->setTimezone(self::REPORT_TIMEZONE)->format($format);
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function priorityReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'priority_reviewed_by')->withTrashed();
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Timestamp used to order the Resolved list newest-resolution-first.
     * Falls back to confirmed_at for records resolved before this column
     * existed.
     */
    public function getResolvedAtForOrderingAttribute(): ?\DateTimeInterface
    {
        return $this->resolved_at ?? $this->confirmed_at;
    }

    public function getEvidenceMediaTypeAttribute(): ?string
    {
        if ($this->media_type) {
            return $this->media_type;
        }

        if (! $this->photo_path) {
            return null;
        }

        $extension = strtolower(pathinfo($this->photo_path, PATHINFO_EXTENSION));

        return in_array($extension, ['mp4', 'mov', 'avi', 'webm'], true) ? 'video' : 'image';
    }
}
