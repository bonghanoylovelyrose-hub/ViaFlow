<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$project = dirname(__DIR__, 3);
require $project.'/vendor/autoload.php';
$app = require $project.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();

function requireCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function jsonFile(string $name): array
{
    return json_decode(file_get_contents(__DIR__.'/'.$name), true, flags: JSON_THROW_ON_ERROR);
}

function verifyDatabase($db, array $manifest, array $hashes, bool $before = false): int
{
    $total = 0;
    foreach ($manifest['primary_keys'] as $table => $key) {
        $query = $db->table($table)->orderBy($key);
        $records = ($before ? $query->lockForUpdate() : $query)->get()
            ->mapWithKeys(fn ($row) => [$row->{$key} => (array) $row])->all();
        $counts = $manifest['record_counts'][$table];
        requireCheck(count($records) === $counts['preserve'] + ($before ? $counts['remove'] : 0)
            && hash('sha256', serialize($records)) === ($hashes[$table] ?? null), "Unexpected record contents or count in $table.");
        $total += count($records);
    }

    return $total;
}

function diskPath(array $file, bool $mustExist): string
{
    $disk = $file['disk'];
    $path = $file['path'];
    requireCheck(in_array($disk, ['local', 'public'], true) && !str_contains($path, ':') && !str_contains($path, '\\')
        && !array_intersect(explode('/', $path), ['', '.', '..']), 'Unexpected upload path.');
    $root = realpath(config("filesystems.disks.$disk.root"));
    requireCheck($root !== false, "The $disk disk is unavailable.");
    $location = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    clearstatcache(true, $location);
    $resolved = realpath($location);
    if ($resolved === false && !$mustExist && !file_exists($location) && !is_link($location)) {
        return $location;
    }
    requireCheck($resolved !== false && is_file($resolved) && strcasecmp($resolved, $location) === 0, "Unexpected upload location on $disk.");
    requireCheck(hash_file('sha256', $resolved) === $file['sha256'], "Upload contents changed on $disk.");

    return $resolved;
}

function verifyUploadBackups(array $files, bool $preDelete): void
{
    foreach ($files as $file) {
        $source = diskPath($file, $preDelete);
        requireCheck(hash_file('sha256', __DIR__.'/consumer-uploads/'.$file['disk'].'/'.$file['path']) === $file['sha256'], 'An upload backup is missing or changed.');
        if ($preDelete) {
            requireCheck(is_writable($source) && is_writable(dirname($source)), 'An affected upload cannot be removed.');
        }
    }
}

$mode = $argv[1] ?? '';
$committed = false;
try {
    $server = $db->selectOne('SELECT DATABASE() AS db, @@hostname AS host, @@port AS port, @@foreign_key_checks AS fk_checks');
    requireCheck($app->environment('local') && parse_url(config('app.url'), PHP_URL_HOST) === '127.0.0.1'
        && $db->getConfig('host') === '127.0.0.1' && $server->db === 'water_billing_systems'
        && strcasecmp($server->host, gethostname()) === 0 && (int) $server->port === 3306 && (int) $server->fk_checks === 1,
        'The intended local database and enabled foreign key checks are required.');
    $manifest = jsonFile('deletion-manifest.json');
    $recheck = jsonFile('rechecked-scope.json');
    $approval = '10e1109a2f9c02a8975b1098ccf99b7599decc9069da8ec08301a29ccdcc2ee3';
    requireCheck($approval === $manifest['scope_sha256'] && $approval === $recheck['scope_sha256']
        && $server->db === $manifest['database'] && hash_file('sha256', __DIR__.'/water_billing_systems.sql') === $manifest['backup_sha256'],
        'The original backup and approved rechecked scope are required.');
    requireCheck(in_array($mode, ['--execute', '--finish-uploads', '--verify'], true)
        && ($mode === '--verify' || ($argv[2] ?? null) === $approval), 'Pass the approved scope hash to execute any deletion.');
    $expectedMeters = ['MT-0909', 'MT-2121', 'MT-3333', 'MT-7777', 'MT-8989', 'MT-9191'];
    $plannedMeters = array_keys($manifest['retained_meters']);
    sort($plannedMeters);
    $selected = $manifest['rows_to_remove'];
    $files = $manifest['files_to_remove'];
    requireCheck($plannedMeters === $expectedMeters && count($selected['consumers']) === 14 && count($selected['users']) === 12
        && array_sum(array_map('count', $selected)) === 443 && count($files) === 51, 'The counts or meters differ from the confirmed preview.');
    verifyUploadBackups($files, $mode === '--execute');
    foreach ($recheck['preserved_files'] as $file) {
        diskPath($file, true);
    }

    if ($mode === '--execute') {
        $order = [
            'jobs', 'failed_jobs', 'notifications', 'password_reset_tokens', 'sessions', 'announcement_recipients', 'announcement_deliveries',
            'loan_repayment_allocations', 'loan_repayments', 'loan_agreements', 'loan_dss_evaluations', 'loan_installments',
            'installation_reschedule_requests', 'consumer_reconnections', 'disconnection_notices', 'payments', 'bills', 'meter_readings',
            'incidents', 'meter_assignments', 'service_requests', 'loan_meter_logs', 'loan_meters', 'consumer_email_verifications',
            'consumer_password_setups', 'consumer_notification_logs', 'loans', 'consumers', 'users',
        ];
        requireCheck(array_diff(array_keys($selected), $order) === [], 'The deletion order does not cover the approved scope.');
        $db->statement('SET SESSION innodb_lock_wait_timeout = 5');
        $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->beginTransaction();
        requireCheck(verifyDatabase($db, $manifest, $recheck['all_row_hashes'], before: true) === 745, 'The database has changed since the recheck.');
        foreach ($order as $table) {
            if ($ids = array_keys($selected[$table] ?? [])) {
                $removed = $db->table($table)->whereIn($manifest['primary_keys'][$table], $ids)->delete();
                requireCheck($removed === count($ids), "Unexpected number of rows deleted from $table.");
            }
        }
        requireCheck(verifyDatabase($db, $manifest, $manifest['preserved_row_hashes']) === 302, 'Preserved database contents failed verification.');
        $db->commit();
        $committed = true;
        echo "Committed exactly 443 approved database row deletions.\n";
    }

    $retainedTotal = verifyDatabase($db, $manifest, $manifest['preserved_row_hashes']);
    requireCheck($db->table('consumers')->orderBy('meter_number')->pluck('meter_number')->all() === $expectedMeters, 'Retained meter verification failed.');

    foreach ($files as $file) {
        $source = diskPath($file, false);
        if ($mode !== '--verify' && is_file($source)) {
            requireCheck(unlink($source), 'An approved upload could not be removed.');
        }
        clearstatcache(true, $source);
        requireCheck(!file_exists($source) && !is_link($source), 'An affected upload is still present.');
    }
    foreach ($recheck['preserved_files'] as $file) {
        diskPath($file, true);
    }
    verifyUploadBackups($files, false);

    printf("Verified %d rows across %d tables, six named consumer accounts, %d preserved uploads, and %d removed uploads with backups intact.\n",
        $retainedTotal, count($manifest['primary_keys']), count($recheck['preserved_files']), count($files));
    echo 'User roles: ', json_encode($db->table('users')->selectRaw('role, COUNT(*) AS total')->groupBy('role')->orderBy('role')->get()), PHP_EOL;
} catch (Throwable $error) {
    if ($db->transactionLevel() > 0) {
        $db->rollBack();
    }
    echo $committed ? 'The database was committed. ' : '';
    echo $error->getMessage(), PHP_EOL;
    exit(1);
}
