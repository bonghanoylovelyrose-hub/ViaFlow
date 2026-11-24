<?php
use Illuminate\Support\Facades\DB;
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = DB::connection();
if (! $app->isLocal() || $db->getDatabaseName() !== 'water_billing_systems' || $db->getConfig('host') !== '127.0.0.1') throw new RuntimeException('Unexpected database');
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
$consumer = $db->table('consumers')->where('meter_number', 'MT-4343')->sole();
echo 'ACCOUNT ', json_encode($consumer), PHP_EOL;
foreach ($db->table('meter_readings')->where('consumer_id', $consumer->id)->orderBy('reading_date')->orderBy('id')->get() as $reading) {
    echo 'READING ', json_encode($reading), PHP_EOL;
    foreach ($db->table('bills')->where('meter_reading_id', $reading->id)->get() as $bill) echo 'BILL ', json_encode($bill), PHP_EOL;
}
foreach ($db->table('bills')->where('consumer_id', $consumer->id)->orderBy('billing_period')->get() as $bill) {
    echo 'BILL ', json_encode($bill), PHP_EOL;
    foreach ($db->table('payments')->where('bill_id', $bill->id)->get() as $payment) echo 'PAYMENT ', json_encode($payment), PHP_EOL;
}
$db->rollBack(0);
