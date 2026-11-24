<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$period = now()->startOfMonth();
echo 'period='.$period->toDateString().PHP_EOL;

foreach (App\Models\Consumer::query()->whereIn('status', App\Models\Consumer::VERIFIED_STATUSES)->orderBy('id')->get() as $consumer) {
    $reading = App\Models\MeterReading::query()
        ->where('consumer_id', $consumer->id)
        ->whereYear('reading_date', $period->year)
        ->whereMonth('reading_date', $period->month)
        ->first();
    echo $consumer->id.' | '.$consumer->full_name.' | meter='.$consumer->meter_number.' | reading='.($reading ? $reading->status.' '.$reading->reading_date : 'none').PHP_EOL;
}
