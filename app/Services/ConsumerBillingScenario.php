<?php

namespace App\Services;

use App\Models\Consumer;

class ConsumerBillingScenario
{
    public function __construct(private readonly DisconnectionRecordsService $notices, private readonly WaterBillingLedger $ledger) {}

    public function isAvailableFor(?Consumer $consumer): bool
    {
        return WaterBillingLedger::isScenarioAccount($consumer)
            && $consumer->bills()->onlyBillingFixtures()->exists();
    }

    public function snapshot(Consumer $consumer): array
    {
        $bills = $this->ledger->withAmounts($this->ledger->bills())->where('consumer_id', $consumer->id)
            ->with('consumer')->orderByDesc('billing_period')->orderByDesc('id')->get();

        return [
            'bills' => $bills,
            'notice' => $this->notices->activeFor($consumer),
        ] + $this->ledger->summary($bills);
    }
}
