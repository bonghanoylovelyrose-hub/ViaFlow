<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasBillingFixtures
{
    protected static function bootHasBillingFixtures(): void
    {
        static::addGlobalScope('billingFixtures', function (Builder $query): void {
            if (! app()->bound('billing.legacySchemaMigration')) {
                $query->where($query->qualifyColumn('is_billing_fixture'), false);
            }
        });
    }

    public function initializeHasBillingFixtures(): void
    {
        $this->mergeCasts(['is_billing_fixture' => 'boolean']);
    }

    public function scopeWithBillingFixtures(Builder $query): Builder
    {
        return $query->when(app()->isLocal(), fn (Builder $query) => $query->withoutGlobalScope('billingFixtures'));
    }

    public function scopeOnlyBillingFixtures(Builder $query): Builder
    {
        return $query->withBillingFixtures()->where($query->qualifyColumn('is_billing_fixture'), true);
    }
}
