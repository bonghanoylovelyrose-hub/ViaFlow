<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\User::query()->orderBy('id')->get() as $user) {
    echo $user->id.' | '.$user->role.' | '.$user->email.' | '.$user->name.PHP_EOL;
}
