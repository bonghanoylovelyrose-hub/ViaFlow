<?php

namespace App\Models;

use App\Models\Concerns\HasBillingFixtures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeterReading extends Model
{
    use HasBillingFixtures;

    public const STATUS_PENDING = 'Pending';

    public const STATUS_ENCODED = 'Encoded';

    public const STATUS_NEEDS_REVISIT = 'Needs Revisit';

    protected $fillable = [
        'consumer_id',
        'meter_number',
        'reader_id',
        'reading_date',
        'submitted_at',
        'previous_reading',
        'current_reading',
        'consumption',
        'status',
        'remarks',
        'meter_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'reading_date' => 'date',
            'submitted_at' => 'datetime',
            'previous_reading' => 'decimal:2',
            'current_reading' => 'decimal:2',
            'consumption' => 'decimal:2',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reader_id')->withTrashed();
    }

    public function bill(): HasOne
    {
        return $this->hasOne(Bill::class)->withBillingFixtures();
    }
}
