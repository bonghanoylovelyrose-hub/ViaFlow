<?php
namespace App\Console\Commands;
use App\Services\WaterBillCycleAdjuster;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class AdjustWaterBillCycle extends Command
{
    protected $signature = 'billing:adjust-water-cycle {bill : Water bill ID} {period : Target month (YYYY-MM)} {--due-date= : Explicit deadline (YYYY-MM-DD)}';
    protected $description = 'Adjust one water bill cycle and its reading, preserving payment history';

    public function handle(WaterBillCycleAdjuster $adjuster): int
    {
        $period = (string) $this->argument('period');
        $due = $this->option('due-date');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) || ($due && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) || ! checkdate((int) substr($due, 5, 2), (int) substr($due, 8, 2), (int) substr($due, 0, 4))))) {
            $this->error('Use YYYY-MM for the period and a valid YYYY-MM-DD for the deadline.');
            return self::FAILURE;
        }
        try {
            $bill = $adjuster->adjust((int) $this->argument('bill'), Carbon::createFromFormat('!Y-m-d', $period.'-01'), $due ? Carbon::parse($due) : null);
            $this->info("Bill {$bill->id}: {$bill->billing_period->format('Y-m')}, due {$bill->due_date->toDateString()}.");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
