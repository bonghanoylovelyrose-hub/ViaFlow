<?php

namespace App\Services;

class BillingComputationService
{
    public const DEFAULT_BASE_CHARGE = 150.00;

    public const MEMBER_UNIT_RATE = 18.00;

    public const NON_MEMBER_UNIT_RATE = 20.00;

    /**
     * Minimum charge and penalty rate are no longer collected or applied to
     * newly generated bills. The constants are retained so historical bills and
     * any external reference keep resolving, but they are deliberately unused.
     */
    public const DEFAULT_MINIMUM_CHARGE = 150.00;

    public const DEFAULT_PENALTY_RATE = 0.10;

    /**
     * Computes a bill total from current consumption plus every peso still owed
     * from earlier periods.
     *
     * Penalties and minimum charges are intentionally excluded: new bills carry
     * the previous outstanding balance and the current month's water charges
     * only.
     */
    public function calculate(
        float $consumption,
        float $baseCharge,
        float $unitRate,
        float $previousBalance,
    ): array {
        $usageCharge = round(max(0, $consumption) * max(0, $unitRate), 2);
        $baseCharge = round(max(0, $baseCharge), 2);
        $previousBalance = round(max(0, $previousBalance), 2);

        $currentCharges = round($baseCharge + $usageCharge, 2);

        return [
            'base_charge' => $baseCharge,
            'usage_charge' => $usageCharge,
            'current_charges' => $currentCharges,
            'previous_balance' => $previousBalance,
            'penalty_amount' => 0.00,
            'total_due' => round($currentCharges + $previousBalance, 2),
        ];
    }
}