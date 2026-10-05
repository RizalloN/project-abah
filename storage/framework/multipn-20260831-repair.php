<?php
// Incident-scoped audit. Never infer a deletion from account number alone.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$root = storage_path('app/private/multipn-20260831-recovery-20261004');
$plan = [
    '00045 -- KC Madiun(Konsolidasi-MB)' => ['old' => '2026-09-07 06:55:07', 'new' => '2026-10-03 20:38:08', 'rows' => 741515],
    '00057 -- KC Ngawi(Konsolidasi-MB)' => ['old' => '2026-09-07 07:03:01', 'new' => '2026-10-03 22:48:08', 'rows' => 698911],
    '00070 -- KC Ponorogo(Konsolidasi-MB)' => ['old' => '2026-09-07 07:08:17', 'new' => '2026-10-03 22:43:18', 'rows' => 789491],
];
$fields = ['posisi', 'regional_office', 'kantor_cabang', 'unit_kerja', 'CIFNO', 'no_rekening', 'jenis_simpanan', 'status', 'saldo_idr'];
if (($argv[1] ?? '') === 'apply-verified-backup') {
    $manifest = json_decode(file_get_contents($root . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($manifest['different_fingerprints'] !== 0 || $manifest['plan'] !== $plan || file_exists($root . '/result.json')) {
        throw new RuntimeException('Missing clean audit or repair already applied.');
    }
    foreach ($manifest['backups'] as $backup) {
        if (!hash_equals($backup['sha256'], hash_file('sha256', $backup['path']))) {
            throw new RuntimeException('Backup checksum mismatch.');
        }
    }
    $audit = new PDO('sqlite:' . $root . '/comparison.sqlite');
    $audit->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $audit->exec('CREATE TABLE IF NOT EXISTS survivors (hash TEXT PRIMARY KEY, n INTEGER NOT NULL) WITHOUT ROWID');
    $audit->exec('DELETE FROM survivors');
    $survivorInsert = $audit->prepare('INSERT INTO survivors VALUES (?, 1) ON CONFLICT(hash) DO UPDATE SET n=n+1');
    $guard = app(App\Services\Import\ImportDuplicateGuardService::class);
    $locks = [];
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    $deleted = [];
    try {
        foreach ($plan as $branch => $batch) {
            $locks[] = $guard->acquireAdvisoryLock('simpanan_multipn', ['posisi' => '2026-08-31', 'kantor_cabang' => $branch]);
        }
        if (Illuminate\Support\Facades\DB::table('import_jobs')->where('id_report', 9)->whereIn('status', ['queued', 'staging', 'processing'])->exists()) {
            throw new RuntimeException('Active MultiPN import; repair deferred.');
        }
        $pdo->beginTransaction();
        foreach ($plan as $branch => $batch) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM simpanan_multipn WHERE posisi=? AND kantor_cabang=? AND created_at=?');
            $scope = ['2026-08-31', $branch, $batch['new']];
            $check->execute($scope);
            if ((int) $check->fetchColumn() !== $batch['rows']) {
                throw new RuntimeException('Candidate count changed after backup.');
            }
            $delete = $pdo->prepare('DELETE FROM simpanan_multipn WHERE posisi=? AND kantor_cabang=? AND created_at=?');
            $delete->execute($scope);
            $deleted[$branch] = $delete->rowCount();
            if ($deleted[$branch] !== $batch['rows']) {
                throw new RuntimeException('Affected rows mismatch.');
            }
            echo 'DELETED_INSIDE_TRANSACTION ' . $branch . ' ' . $deleted[$branch] . PHP_EOL;
        }
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $query = $pdo->prepare('SELECT * FROM simpanan_multipn WHERE posisi=?');
        $query->execute(['2026-08-31']);
        $audit->beginTransaction();
        $remaining = [];
        $done = 0;
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $branch = $row['kantor_cabang'];
            $remaining[$branch] = ($remaining[$branch] ?? 0) + 1;
            if (isset($plan[$branch])) {
                if ($row['created_at'] !== $plan[$branch]['old']) {
                    throw new RuntimeException('Unexpected survivor batch.');
                }
                $values = array_map(static fn ($field) => $row[$field], $fields);
                $survivorInsert->execute([hash('sha256', json_encode($values, JSON_THROW_ON_ERROR))]);
            }
            if (++$done % 100000 === 0) {
                $audit->commit();
                echo 'VERIFIED_SURVIVORS ' . $done . PHP_EOL;
                $audit->beginTransaction();
            }
        }
        $query->closeCursor();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $audit->commit();
        $mismatches = (int) $audit->query('SELECT COUNT(*) FROM fingerprints f LEFT JOIN survivors s ON s.hash=f.hash WHERE COALESCE(s.n,0)<>f.old_count')->fetchColumn();
        $extras = (int) $audit->query('SELECT COUNT(*) FROM survivors s LEFT JOIN fingerprints f ON s.hash=f.hash WHERE f.hash IS NULL')->fetchColumn();
        foreach ($plan as $branch => $batch) {
            if (($remaining[$branch] ?? 0) !== $batch['rows']) {
                throw new RuntimeException('Baseline branch count changed.');
            }
        }
        foreach ($manifest['untouched'] as $branch => $count) {
            if (($remaining[$branch] ?? 0) !== $count) {
                throw new RuntimeException('Untouched branch count changed.');
            }
        }
        if ($mismatches !== 0 || $extras !== 0) {
            throw new RuntimeException('Survivors differ from original baseline.');
        }
        $pdo->commit();
        $result = ['committed_at' => date(DATE_ATOM), 'deleted' => $deleted, 'remaining' => $remaining, 'different_fingerprints' => $mismatches, 'unexpected_fingerprints' => $extras];
        file_put_contents($root . '/result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        foreach (array_reverse($locks) as $lock) {
            $guard->releaseAdvisoryLock($lock);
        }
    }
    exit;
}
if (($argv[1] ?? '') !== 'audit') {
    throw new RuntimeException('Only audit is enabled.');
}
if (file_exists($root)) {
    throw new RuntimeException('Audit directory already exists; do not overwrite evidence.');
}
mkdir($root, 0700, true);
$audit = new PDO('sqlite:' . $root . '/comparison.sqlite');
$audit->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$audit->exec('PRAGMA journal_mode=WAL');
$audit->exec('PRAGMA synchronous=NORMAL');
$audit->exec('CREATE TABLE fingerprints (hash TEXT PRIMARY KEY, old_count INTEGER NOT NULL, new_count INTEGER NOT NULL) WITHOUT ROWID');
$upsert = $audit->prepare('INSERT INTO fingerprints VALUES (?, ?, ?) ON CONFLICT(hash) DO UPDATE SET old_count=old_count+excluded.old_count, new_count=new_count+excluded.new_count');
$backups = [];
$counts = [];
foreach ($plan as $branch => $batch) {
    $path = $root . '/' . substr($branch, 0, 5) . '-duplicate-batch.jsonl.gz';
    $backups[$branch] = ['path' => $path, 'handle' => gzopen($path, 'wb6')];
    $counts[$branch] = ['old' => 0, 'new' => 0];
}
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
$query = $pdo->prepare('SELECT * FROM simpanan_multipn WHERE posisi = ?');
$query->execute(['2026-08-31']);
$audit->beginTransaction();
$rows = 0;
$other = [];
while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $branch = $row['kantor_cabang'];
    if (!isset($plan[$branch])) {
        $other[$branch] = ($other[$branch] ?? 0) + 1;
        continue;
    }
    $batch = $plan[$branch];
    $kind = $row['created_at'] === $batch['old'] ? 'old' : ($row['created_at'] === $batch['new'] ? 'new' : null);
    if ($kind === null) {
        throw new RuntimeException('Unexpected batch: ' . $branch . ' ' . $row['created_at']);
    }
    $values = array_map(static fn ($field) => $row[$field], $fields);
    $hash = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    $upsert->execute([$hash, (int) ($kind === 'old'), (int) ($kind === 'new')]);
    $counts[$branch][$kind]++;
    if ($kind === 'new') {
        $line = json_encode($row, JSON_THROW_ON_ERROR) . "\n";
        if (gzwrite($backups[$branch]['handle'], $line) !== strlen($line)) {
            throw new RuntimeException('Backup write failed.');
        }
    }
    if (++$rows % 100000 === 0) {
        $audit->commit();
        echo 'AUDITED ' . $rows . PHP_EOL;
        $audit->beginTransaction();
    }
}
$query->closeCursor();
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
$audit->commit();
$mismatches = (int) $audit->query('SELECT COUNT(*) FROM fingerprints WHERE old_count <> new_count')->fetchColumn();
$backupInfo = [];
foreach ($backups as $branch => $backup) {
    gzclose($backup['handle']);
    $read = gzopen($backup['path'], 'rb');
    $verified = 0;
    while (($line = gzgets($read)) !== false) {
        $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        if ($record['posisi'] !== '2026-08-31' || $record['kantor_cabang'] !== $branch
            || $record['created_at'] !== $plan[$branch]['new']) {
            throw new RuntimeException('Backup scope mismatch.');
        }
        $verified++;
    }
    gzclose($read);
    if ($verified !== $plan[$branch]['rows'] || $counts[$branch]['old'] !== $verified || $counts[$branch]['new'] !== $verified) {
        throw new RuntimeException('Batch count mismatch.');
    }
    $backupInfo[$branch] = ['path' => $backup['path'], 'rows' => $verified, 'sha256' => hash_file('sha256', $backup['path'])];
}
$manifest = ['period' => '2026-08-31', 'audited_at' => date(DATE_ATOM), 'counts' => $counts, 'untouched' => $other, 'different_fingerprints' => $mismatches, 'backups' => $backupInfo, 'plan' => $plan];
file_put_contents($root . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
if ($mismatches !== 0) {
    throw new RuntimeException('Audit differs: deletion is forbidden.');
}
