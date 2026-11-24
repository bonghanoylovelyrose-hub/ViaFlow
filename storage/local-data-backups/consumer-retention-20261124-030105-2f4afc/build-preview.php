<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$project = dirname(__DIR__, 3);
require $project.'/vendor/autoload.php';
$app = require $project.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
$server = $db->selectOne('SELECT DATABASE() AS db, @@hostname AS host, @@port AS port, @@version AS version');
$recheck = ($argv[1] ?? null) === '--recheck';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function putBackup(string $path, string $content): void
{
    check(file_put_contents($path, $content, LOCK_EX) === strlen($content), 'Could not write '.basename($path));
}

function documentPaths(array $row, array $columns): array
{
    $paths = [];
    foreach ($columns as $column) {
        $value = $row[$column];
        if ($column === 'membership_document_paths' && is_string($value)) {
            $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        }
        foreach ((array) $value as $path) {
            if (is_string($path) && trim($path) !== '') {
                $paths[] = str_replace('\\', '/', $path);
            }
        }
    }

    return $paths;
}

try {
    check($app->environment('local') && parse_url(config('app.url'), PHP_URL_HOST) === '127.0.0.1'
        && $server->db === 'water_billing_systems' && strcasecmp($server->host, gethostname()) === 0
        && $db->getConfig('host') === '127.0.0.1' && (int) $server->port === 3306, 'Database is not the intended local instance.');
    $backup = __DIR__.'/water_billing_systems.sql';
    $backupHash = hash_file('sha256', $backup);
    check(str_starts_with(file_get_contents(__DIR__.'/water_billing_systems.sha256'), $backupHash.'  '), 'Database backup checksum mismatch.');
    $approved = $recheck ? json_decode(file_get_contents(__DIR__.'/deletion-manifest.json'), true, flags: JSON_THROW_ON_ERROR) : [];

    $db->statement('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    $keys = $rows = $selected = [];
    foreach ($db->select("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY TABLE_NAME, ORDINAL_POSITION") as $key) {
        check(!isset($keys[$key->TABLE_NAME]), 'Compound primary key requires review.');
        $keys[$key->TABLE_NAME] = $key->COLUMN_NAME;
        $records = $db->table($key->TABLE_NAME)->orderBy($key->COLUMN_NAME)->get();
        $rows[$key->TABLE_NAME] = $records->mapWithKeys(fn ($row) => [$row->{$key->COLUMN_NAME} => (array) $row])->all();
    }
    check(count($keys) === 36, 'Database tables changed since the backup.');

    $keepers = ['MT-8989', 'MT-2121', 'MT-3333', 'MT-7777', 'MT-0909', 'MT-9191'];
    $kept = $keptUsers = $removedUsers = [];
    foreach ($rows['consumers'] as $id => $row) {
        if (in_array($row['meter_number'], $keepers, true)) {
            check(!isset($kept[$row['meter_number']]), 'A retained meter number is duplicated.');
            $user = $rows['users'][$row['user_id']] ?? [];
            check(($user['role'] ?? null) === 'consumer' && $user['deleted_at'] === null, 'A retained consumer has no usable linked user record.');
            $kept[$row['meter_number']] = $id;
            $keptUsers[$row['user_id']] = $id;
        } else {
            $selected['consumers'][$id] = $id;
            if ($row['user_id'] !== null && ($rows['users'][$row['user_id']]['role'] ?? null) === 'consumer') {
                $removedUsers[$row['user_id']] = $id;
            }
        }
    }
    check(count($kept) === 6 && count($keptUsers) === 6, 'One or more expected accounts or logins is missing.');
    $selected['users'] = array_diff_key($removedUsers, $keptUsers);
    foreach ($rows['users'] as $id => $user) {
        check($user['role'] !== 'consumer' || isset($keptUsers[$id]) || isset($selected['users'][$id]), 'Unlinked consumer user requires ownership review.');
    }

    $ownedThrough = [
        'bills' => ['consumer_id', 'consumers'],
        'consumer_email_verifications' => ['consumer_id', 'consumers'],
        'consumer_notification_logs' => ['consumer_id', 'consumers'],
        'consumer_password_setups' => ['consumer_id', 'consumers'],
        'consumer_reconnections' => ['consumer_id', 'consumers'],
        'disconnection_notices' => ['consumer_id', 'consumers'],
        'incidents' => ['consumer_id', 'consumers'],
        'installation_reschedule_requests' => ['consumer_id', 'consumers'],
        'loans' => ['consumer_id', 'consumers'],
        'loan_meters' => ['consumer_id', 'consumers'],
        'meter_assignments' => ['consumer_id', 'consumers'],
        'meter_readings' => ['consumer_id', 'consumers'],
        'payments' => ['consumer_id', 'consumers'],
        'service_requests' => ['consumer_id', 'consumers'],
        'loan_agreements' => ['loan_id', 'loans'],
        'loan_dss_evaluations' => ['loan_id', 'loans'],
        'loan_installments' => ['loan_id', 'loans'],
        'loan_repayments' => ['loan_id', 'loans'],
        'loan_meter_logs' => ['loan_meter_id', 'loan_meters'],
        'loan_repayment_allocations' => ['loan_repayment_id', 'loan_repayments'],
        'announcement_deliveries' => ['user_id', 'users'],
        'announcement_recipients' => ['user_id', 'users'],
        'sessions' => ['user_id', 'users'],
    ];
    foreach ($ownedThrough as $table => [$column, $parent]) {
        foreach ($rows[$table] as $id => $row) {
            if (isset($selected[$parent][$row[$column]])) {
                $selected[$table][$id] = $selected[$parent][$row[$column]];
            }
        }
    }

    $emailOwners = $protectedEmails = [];
    foreach (['consumers', 'users'] as $table) {
        foreach ($rows[$table] as $id => $row) {
            if (!filled($row['email'])) {
                continue;
            }
            $email = mb_strtolower(trim($row['email']));
            if (isset($selected[$table][$id])) {
                $emailOwners[$email] = $selected[$table][$id];
            } else {
                $protectedEmails[$email] = true;
            }
        }
    }
    foreach ($rows['password_reset_tokens'] as $email => $row) {
        $normalized = mb_strtolower(trim($email));
        if (isset($emailOwners[$normalized]) && !isset($protectedEmails[$normalized])) {
            $selected['password_reset_tokens'][$email] = $emailOwners[$normalized];
        }
    }
    $notificationSubjects = [
        'consumer_id' => 'consumers', 'loan_id' => 'loans', 'payment_id' => 'payments', 'incident_id' => 'incidents',
        'meter_reading_id' => 'meter_readings', 'reschedule_request_id' => 'installation_reschedule_requests',
        'service_request_id' => 'service_requests',
    ];
    $staffNotificationCopies = 0;
    foreach ($rows['notifications'] as $id => $row) {
        $owner = $row['notifiable_type'] === App\Models\User::class ? ($selected['users'][$row['notifiable_id']] ?? null) : null;
        $data = json_decode($row['data'], true, flags: JSON_THROW_ON_ERROR);
        foreach ($notificationSubjects as $field => $table) {
            if (isset($data[$field], $selected[$table][$data[$field]])) {
                $subject = $selected[$table][$data[$field]];
                check($owner === null || $owner === $subject, 'Notification has multiple affected consumer owners.');
                $owner = $subject;
            }
        }
        if ($owner !== null) {
            check(!isset($keptUsers[$row['notifiable_id']]), 'A retained login received a notification about an affected record.');
            $selected['notifications'][$id] = $owner;
            $staffNotificationCopies += isset($selected['users'][$row['notifiable_id']]) ? 0 : 1;
        }
    }

    foreach (['jobs', 'failed_jobs'] as $table) {
        foreach ($rows[$table] as $id => $row) {
            $payload = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
            check(($payload['displayName'] ?? null) === App\Jobs\SendAnnouncementEmail::class
                && preg_match('/"deliveryId";i:(\d+);/', $payload['data']['command'] ?? '', $match) === 1, 'Unclassified queue job requires review.');
            if (isset($selected['announcement_deliveries'][$match[1]])) {
                check(($row['reserved_at'] ?? null) === null, 'An affected queue job is reserved by a worker.');
                $selected[$table][$id] = $selected['announcement_deliveries'][$match[1]];
            }
        }
    }
    foreach ($rows['announcements'] as $row) {
        $recipients = json_decode($row['recipient_user_ids'] ?? '[]', true, flags: JSON_THROW_ON_ERROR) ?? [];
        check(array_intersect($recipients, array_keys($selected['users'])) === [], 'A shared announcement recipient list needs review.');
    }
    foreach ($kept as $id) {
        foreach (['membership_reviewed_by', 'installation_completed_by'] as $column) {
            check(!isset($selected['users'][$rows['consumers'][$id][$column]]), 'A retained consumer history references an affected login.');
        }
    }
    foreach ($rows['consumer_reconnections'] as $id => $row) {
        if (!isset($selected['consumer_reconnections'][$id])) {
            $paymentIds = json_decode($row['verified_payment_ids'] ?? '[]', true, flags: JSON_THROW_ON_ERROR) ?? [];
            check(array_intersect($paymentIds, array_keys($selected['payments'] ?? [])) === [], 'A preserved reconnection references an affected payment.');
        }
    }

    $foreignKeys = $db->select("SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, COLUMN_NAME");
    $foreignKeyConflicts = [];
    foreach ($foreignKeys as $fk) {
        $table = $fk->TABLE_NAME;
        $parent = $fk->REFERENCED_TABLE_NAME;
        check($fk->REFERENCED_COLUMN_NAME === $keys[$parent], 'A foreign key references a non-primary key.');
        foreach ($rows[$table] as $id => $row) {
            if (!isset($selected[$table][$id]) && isset($selected[$parent][$row[$fk->COLUMN_NAME]])) {
                $foreignKeyConflicts[] = "$table $id {$fk->COLUMN_NAME} → $parent {$row[$fk->COLUMN_NAME]}";
            }
        }
    }
    check($foreignKeyConflicts === [], 'Preserved foreign key rows require review: '.implode(', ', $foreignKeyConflicts));
    $db->commit();

    $uploadColumns = [
        'consumers' => ['valid_id_path', 'membership_document_paths', 'proof_of_residency_path', 'other_document_path'],
        'loans' => ['proof_of_income_path'], 'payments' => ['proof_path'], 'meter_readings' => ['meter_photo_path'],
        'service_requests' => ['proof_path'], 'incidents' => ['photo_path'], 'announcements' => ['photo_path'],
    ];
    $candidates = $protected = [];
    foreach ($uploadColumns as $table => $columns) {
        foreach ($rows[$table] as $id => $row) {
            foreach (documentPaths($row, $columns) as $path) {
                if (isset($selected[$table][$id])) {
                    $candidates[mb_strtolower($path)] = $path;
                } else {
                    $protected[mb_strtolower($path)] = $path;
                }
            }
        }
    }
    $exclusivePaths = array_diff_key($candidates, $protected);
    $files = $missing = [];
    foreach ($exclusivePaths as $path) {
        check(!str_contains($path, ':') && !array_intersect(explode('/', $path), ['', '.', '..']), 'Invalid upload path requires manual review.');
        $found = false;
        // Look for exact-path legacy copies on either local disk.
        foreach (['local', 'public'] as $disk) {
            $root = realpath(config("filesystems.disks.$disk.root"));
            check($root !== false, "Could not locate the $disk disk.");
            $source = realpath($root.'/'.$path);
            if ($source === false) {
                continue;
            }
            check(is_file($source) && strncasecmp($source, $root.DIRECTORY_SEPARATOR, strlen($root) + 1) === 0, 'Upload resolves outside its expected disk.');
            $found = true;
            $destination = __DIR__.'/consumer-uploads/'.$disk.'/'.$path;
            $hash = hash_file('sha256', $source);
            if (!$recheck) {
                check(is_dir(dirname($destination)) || mkdir(dirname($destination), 0700, true), 'Could not prepare upload backup directory.');
                check(copy($source, $destination), 'Could not copy an upload to the backup.');
            }
            check(is_string($hash) && is_file($destination) && hash_file('sha256', $destination) === $hash, 'Upload backup checksum mismatch.');
            $files[$disk.'/'.$path] = ['disk' => $disk, 'path' => $path, 'bytes' => filesize($source), 'sha256' => $hash];
        }
        if (!$found) {
            $missing[] = $path;
        }
    }

    ksort($selected);
    ksort($files);
    sort($missing);
    $fingerprint = hash('sha256', serialize([$kept, $selected, $files, $missing]));
    $counts = $preservedHashes = $allHashes = [];
    foreach ($rows as $table => $records) {
        $deleted = $selected[$table] ?? [];
        $counts[$table] = ['remove' => count($deleted), 'preserve' => count($records) - count($deleted)];
        $preservedHashes[$table] = hash('sha256', serialize(array_diff_key($records, $deleted)));
        $allHashes[$table] = hash('sha256', serialize($records));
    }
    $manifest = ['database' => $server->db, 'backup_sha256' => $backupHash, 'scope_sha256' => $fingerprint, 'retained_meters' => $kept,
        'primary_keys' => $keys, 'rows_to_remove' => $selected, 'record_counts' => $counts, 'preserved_row_hashes' => $preservedHashes,
        'files_to_remove' => array_values($files), 'missing_document_paths' => $missing];
    if ($recheck) {
        check($manifest === $approved, 'Current account, row, or file scope differs from the approved deletion preview.');
        $preservedFiles = [];
        foreach ($protected as $path) {
            check(!str_contains($path, ':') && !array_intersect(explode('/', $path), ['', '.', '..']), 'Invalid preserved upload path requires review.');
            foreach (['local', 'public'] as $disk) {
                $root = realpath(config("filesystems.disks.$disk.root"));
                $source = realpath($root.'/'.$path);
                if ($source !== false) {
                    check(is_file($source) && strncasecmp($source, $root.DIRECTORY_SEPARATOR, strlen($root) + 1) === 0, 'Preserved upload resolves outside its expected disk.');
                    $preservedFiles[$disk.'/'.$path] = ['disk' => $disk, 'path' => $path, 'sha256' => hash_file('sha256', $source)];
                }
            }
        }
        putBackup(__DIR__.'/rechecked-scope.json', json_encode(['scope_sha256' => $fingerprint, 'all_row_hashes' => $allHashes,
            'preserved_files' => array_values($preservedFiles)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
        printf("Approved scope matched: %d consumer accounts, %d database rows, %d uploads. Captured hashes for %d preserved uploads.\n",
            count($selected['consumers']), array_sum(array_column($counts, 'remove')), count($files), count($preservedFiles));
        exit(0);
    }
    putBackup(__DIR__.'/deletion-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

    $lines = ['# Local consumer account deletion preview', '', 'No deletion has been executed. Explicit confirmation is required.', '',
        'Database `water_billing_systems` on `127.0.0.1:3306` in the Laravel `local` environment.',
        'Database backup `water_billing_systems.sql` contains all 36 tables and their data. SHA-256 `'.$backupHash.'`.',
        'Deletion scope SHA-256 `'.$fingerprint.'`.', '', '## Accounts to retain', '',
        '| Meter number | Consumer ID | Status |', '| --- | ---: | --- |'];
    foreach ($keepers as $meter) {
        $id = $kept[$meter];
        $lines[] = "| $meter | $id | {$rows['consumers'][$id]['status']} |";
    }
    array_push($lines, '', '## Accounts to remove', '', '| Consumer ID | Meter number | Status | Rows to remove |', '| ---: | --- | --- | ---: |');
    $totalsByAccount = array_count_values(array_merge(...array_map('array_values', array_values($selected))));
    foreach ($selected['consumers'] as $id) {
        $row = $rows['consumers'][$id];
        $meter = $row['meter_number'] ?? 'Unassigned';
        $lines[] = "| $id | $meter | {$row['status']} | {$totalsByAccount[$id]} |";
    }
    $totalRemoved = array_sum(array_column($counts, 'remove'));
    array_push($lines, '', '## Rows to remove', '', '| Table | Remove | Preserve |', '| --- | ---: | ---: |');
    foreach ($counts as $table => $count) {
        if ($count['remove'] > 0) {
            $lines[] = "| $table | {$count['remove']} | {$count['preserve']} |";
        }
    }
    $lines[] = '| **Total removed** | **'.$totalRemoved.'** | |';
    array_push($lines, '', "Notification deletions include $staffNotificationCopies staff-facing copies tied to the affected records. Staff login rows, retained consumer logins, settings, rates, and the shared announcement are preserved.",
        '', '## Uploaded files', '', count($files).' existing files are referenced exclusively by affected records. All were copied and checksum-verified in `consumer-uploads`.',
        count($missing).' exclusive document paths are already missing from both upload disks.',
        count(array_intersect_key($candidates, $protected)).' document paths shared with preserved records are protected.', '',
        'The foreign key audit found no preserved rows depending on rows marked for deletion.');
    putBackup(__DIR__.'/deletion-preview.md', implode(PHP_EOL, $lines).PHP_EOL);
    echo implode(PHP_EOL, $lines), PHP_EOL;
} catch (Throwable $error) {
    if ($db->transactionLevel() > 0) {
        $db->rollBack();
    }
    echo $error->getMessage(), PHP_EOL;
    exit(1);
}
