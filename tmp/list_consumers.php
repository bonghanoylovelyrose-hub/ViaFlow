<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\Consumer::query()->with('user')->orderBy('id')->get() as $consumer) {
    echo $consumer->id.' | '.$consumer->full_name.' | '.$consumer->status.' | meter='.$consumer->meter_number.' | acct='.$consumer->account_number.' | email='.optional($consumer->user)->email.PHP_EOL;
}
