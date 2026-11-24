<?php

namespace App\Services;

use App\Models\LoanMaterialRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanMaterialRateService
{
    public const TERMS = [3, 6, 12];

    public function activeRate(): LoanMaterialRate
    {
        return LoanMaterialRate::query()
            ->where('is_active', true)
            ->latest('id')
            ->first()
            ?? LoanMaterialRate::query()->create([
                'water_meter_cost' => 0,
                'hose_cost' => 0,
                'other_materials_cost' => 0,
                'is_active' => true,
            ]);
    }

    public function updateActiveRate(array $costs, User $user): LoanMaterialRate
    {
        return DB::transaction(function () use ($costs, $user) {
            $hoseCost = $costs['hose_cost'] ?? LoanMaterialRate::query()
                ->where('is_active', true)->latest('id')->lockForUpdate()->value('hose_cost') ?? 0;

            if (round((float) $costs['water_meter_cost'] + (float) $hoseCost + (float) $costs['other_materials_cost'], 2) <= 0) {
                throw ValidationException::withMessages(['loan_material_rates' => 'The total installation cost must be greater than P 0.00.']);
            }

            LoanMaterialRate::query()->where('is_active', true)->update(['is_active' => false]);

            return LoanMaterialRate::query()->create([
                'water_meter_cost' => $this->money($costs['water_meter_cost']),
                'hose_cost' => $this->money($hoseCost),
                'other_materials_cost' => $this->money($costs['other_materials_cost']),
                'is_active' => true,
                'configured_by_user_id' => $user->id,
            ]);
        });
    }

    public function breakdown(LoanMaterialRate $rate): array
    {
        $meter = $this->money($rate->water_meter_cost);
        $hose = $this->money($rate->hose_cost);
        $other = $this->money($rate->other_materials_cost);

        return [
            'meter' => $meter,
            'hose' => $hose,
            'other_materials' => $other,
            'total_installation_cost' => $this->money($meter + $hose + $other),
        ];
    }

    public function costsAreAvailable(array $breakdown): bool
    {
        return $this->money($breakdown['total_installation_cost'] ?? 0) > 0;
    }

    public function schedules(float $total): array
    {
        return collect(self::TERMS)
            ->mapWithKeys(fn (int $term) => [$term => $this->schedule($total, $term)])
            ->all();
    }

    public function schedule(float $total, int $termMonths): array
    {
        if (! in_array($termMonths, self::TERMS, true)) {
            return [];
        }

        $totalCents = (int) round($total * 100);
        $regularCents = (int) round($totalCents / $termMonths);
        $installments = [];

        for ($month = 1; $month <= $termMonths; $month++) {
            $amountCents = $month === $termMonths
                ? max(0, $totalCents - ($regularCents * ($termMonths - 1)))
                : $regularCents;

            $installments[] = [
                'month' => $month,
                'amount' => round($amountCents / 100, 2),
            ];
        }

        return $installments;
    }

    public function snapshotForTerm(int $termMonths): array
    {
        $rate = $this->activeRate();
        $breakdown = $this->breakdown($rate);
        $schedule = $this->schedule($breakdown['total_installation_cost'], $termMonths);

        return [
            ...$breakdown,
            'term_months' => $termMonths,
            'regular_monthly_installment' => $schedule[0]['amount'] ?? 0,
            'schedule' => $schedule,
        ];
    }

    private function money(float|string|null $value): float
    {
        return round((float) $value, 2);
    }
}
