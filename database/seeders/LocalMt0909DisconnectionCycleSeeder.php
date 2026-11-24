<?php

namespace Database\Seeders;

class LocalMt0909DisconnectionCycleSeeder extends LocalWaterDisconnectionCycleSeeder
{
    protected const USE_RECORDED_ISSUE_DATE = true;

    protected function account(): array
    {
        return ['meter' => 'MT-0909', 'name' => 'Ren Rey', 'original_prn' => 'PRN-2611-0050'];
    }
}
