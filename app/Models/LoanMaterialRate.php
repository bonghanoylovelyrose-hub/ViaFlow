<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanMaterialRate extends Model
{
    protected $fillable = [
        'water_meter_cost',
        'hose_cost',
        'other_materials_cost',
        'is_active',
        'configured_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'water_meter_cost' => 'decimal:2',
            'hose_cost' => 'decimal:2',
            'other_materials_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by_user_id')->withTrashed();
    }
}
