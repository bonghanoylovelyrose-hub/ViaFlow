<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Consumer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReconcileLocalNovemberWaterBillsSeeder extends Seeder
{
    public function run(): void
    {
        $db = DB::connection();
        if (! app()->isLocal() || $db->getDatabaseName() !== 'water_billing_systems'
            || $db->getConfig('host') !== '127.0.0.1' || $db->getDriverName() !== 'mysql'
            || strcasecmp($db->selectOne('SELECT @@hostname AS host')->host, gethostname()) !== 0) {
            throw new RuntimeException('This reconciliation requires the local ViaFlow development database.');
        }

        $db->transaction(function (): void {
            foreach (['MT-0909' => 'PRN-2611-0050', 'MT-9191' => 'PRN-2611-0051', 'MT-7777' => 'PRN-2611-0005'] as $meter => $prn) {
                $consumer = Consumer::query()->where('meter_number', $meter)->lockForUpdate()->sole();
                $cycle = fn () => $consumer->bills()->where('bill_type', 'Water')->whereDate('billing_period', '2026-11-01');
                $original = $cycle()->where('prn', $prn)->lockForUpdate()->sole();
                $replacement = $cycle()->onlyBillingFixtures()->lockForUpdate()->sole();
                if ($replacement->superseded_by_bill_id !== null
                    && $replacement->superseded_by_bill_id === $original->superseded_by_bill_id
                    && $cycle()->whereKey($replacement->superseded_by_bill_id)->whereNull('superseded_by_bill_id')->exists()) {
                    continue;
                }
                if (! $replacement->is_demo || $replacement->superseded_by_bill_id !== null
                    || ($original->superseded_by_bill_id !== null && $original->superseded_by_bill_id !== $replacement->id)) {
                    throw new RuntimeException("Unexpected November billing history for {$meter}.");
                }
                if ($original->superseded_by_bill_id === null) {
                    // Keep the contents and payment references of the original issued bill intact.
                    Bill::query()->whereKey($original->id)->toBase()->update(['superseded_by_bill_id' => $replacement->id]);
                }
            }
        });
    }
}
