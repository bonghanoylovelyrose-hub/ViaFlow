<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = 'qa.meter.reader@example.com';

App\Models\User::query()->where('email', $email)->delete();

$user = App\Models\User::factory()->create([
    'name' => 'QA Meter Reader',
    'email' => $email,
    'password' => Illuminate\Support\Facades\Hash::make('password'),
    'role' => 'meter_reader',
    'email_verified_at' => now(),
]);

echo 'created '.$user->id.' '.$user->email.PHP_EOL;
