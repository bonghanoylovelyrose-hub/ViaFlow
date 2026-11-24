<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\Loan;
use App\Services\OverdueMonitoringService;
use Illuminate\Console\Command;

/**
 * Daily overdue sweep: warns consumers whose bills have passed their due date
 * and issues disconnection notices to accounts that qualify.
 *
 * Notices are de-duplicated per bill, so running this repeatedly is safe.
 */
class MonitorOverdueBillingCommand extends Command
{
    protected $signature = 'billing:monitor-overdue';

    protected $description = 'Send overdue reminders and disconnection notices for unpaid bills.';

    public function handle(OverdueMonitoringService $monitoring): int
    {
        $consumerIds = Bill::query()
            ->forCollection()
            ->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])
            ->distinct()
            ->pluck('consumer_id')
            ->merge(DisconnectionNotice::query()->active()->pluck('consumer_id'))
            ->merge(Loan::query()->withExpiredUnpaidSchedule()->distinct()->pluck('consumer_id'))
            ->unique();

        if ($consumerIds->isEmpty()) {
            $this->info('No unpaid bills found.');

            return self::SUCCESS;
        }

        $consumers = Consumer::query()->whereIn('id', $consumerIds)->get();
        $flagged = 0;

        foreach ($consumers as $consumer) {
            // Notices are dispatched here; each event only sends once.
            $result = $monitoring->evaluate($consumer, notify: true);

            if ($result['flagged']) {
                $flagged++;
            }
        }

        $this->info(sprintf(
            'Checked %d consumer(s); %d flagged for disconnection.',
            $consumers->count(),
            $flagged,
        ));

        return self::SUCCESS;
    }
}
