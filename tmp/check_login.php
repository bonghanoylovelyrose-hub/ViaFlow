<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach ([
    ['email' => 'meter.reader@example.com', 'password' => 'password'],
    ['email' => 'admin@example.com', 'password' => 'password'],
    ['email' => 'reader@example.com', 'password' => 'password'],
] as $credentials) {
    echo $credentials['email'].': '.(Illuminate\Support\Facades\Auth::attempt($credentials) ? 'OK' : 'FAIL').PHP_EOL;
    Illuminate\Support\Facades\Auth::logout();
}
