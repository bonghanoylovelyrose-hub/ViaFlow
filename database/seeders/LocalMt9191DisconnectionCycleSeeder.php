<?php

namespace Database\Seeders;

class LocalMt9191DisconnectionCycleSeeder extends LocalWaterDisconnectionCycleSeeder
{
    protected function account(): array
    {
        return ['meter' => 'MT-9191', 'name' => 'Gray Hunt', 'original_prn' => 'PRN-2611-0051'];
    }
}
