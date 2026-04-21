<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function decimalStringToCents(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $negative = str_starts_with($value, '-');
    if ($negative) {
        $value = substr($value, 1);
    }

    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    $whole = preg_replace('/\D+/', '', $whole) ?: '0';
    $fraction = substr(str_pad((string) preg_replace('/\D+/', '', $fraction), 2, '0', STR_PAD_RIGHT), 0, 2);
    $cents = ((int) $whole * 100) + (int) $fraction;

    return $negative ? -$cents : $cents;
}

function formatCents(int $cents): string
{
    $negative = $cents < 0;
    $absolute = abs($cents);
    $whole = intdiv($absolute, 100);
    $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

    return ($negative ? '-' : '') . $whole . '.' . $fraction;
}

$sourcePath = $argv[1] ?? '';
if ($sourcePath === '' || !is_file($sourcePath)) {
    fail('Usage: php scratch/import_simpanan_multipn_fast.php <absolute-csv-path>');
}

$python = 'python';
$scriptPath = base_path('scripts/simpanan_multipn_polars_processor.py');
$tempDir = storage_path('app/temp');
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0777, true);
}

$normalizedCsvPath = $tempDir . DIRECTORY_SEPARATOR . 'simpanan_multipn_fast_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.csv';
$configPath = storage_path('app/simpanan_multipn_fast_' . uniqid('', true) . '.json');

$config = [
    'file_path' => $sourcePath,
    'delimiter' => ';',
    'output_csv_path' => $normalizedCsvPath,
];

file_put_contents($configPath, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$cmd = escapeshellarg($python)
    . ' ' . escapeshellarg($scriptPath)
    . ' --config ' . escapeshellarg($configPath)
    . ' --mode stage';

$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$stageStart = microtime(true);
$process = proc_open($cmd, $descriptorSpec, $pipes, base_path());
if (!is_resource($process)) {
    @unlink($configPath);
    fail('Gagal menjalankan sanitizer Python Simpanan MultiPN.');
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
@unlink($configPath);

if ($exitCode !== 0) {
    fail("Sanitizer gagal.\nSTDERR:\n{$stderr}\nSTDOUT:\n{$stdout}");
}

$donePayload = null;
foreach (preg_split('/\r\n|\r|\n/', (string) $stdout) as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $decoded = json_decode($line, true);
    if (!is_array($decoded)) {
        continue;
    }

    if (($decoded['type'] ?? '') === 'done') {
        $donePayload = $decoded;
    }
}

if (!is_array($donePayload) || !is_file($normalizedCsvPath)) {
    fail("Sanitizer tidak menghasilkan payload selesai yang valid.\nSTDOUT:\n{$stdout}\nSTDERR:\n{$stderr}");
}

$normalizedHandle = fopen($normalizedCsvPath, 'rb');
if ($normalizedHandle === false) {
    @unlink($normalizedCsvPath);
    fail('Gagal membuka CSV hasil normalisasi.');
}

$headers = fgetcsv($normalizedHandle, 0, ';');
fclose($normalizedHandle);
if ($headers === false || $headers === []) {
    @unlink($normalizedCsvPath);
    fail('Header CSV hasil normalisasi tidak ditemukan.');
}

$stageElapsed = microtime(true) - $stageStart;
$periods = [];
$periodHandle = fopen($normalizedCsvPath, 'rb');
if ($periodHandle !== false) {
    $headerRow = fgetcsv($periodHandle, 0, ';');
    $periodIndex = is_array($headerRow) ? array_search('posisi', $headerRow, true) : false;
    while (($row = fgetcsv($periodHandle, 0, ';')) !== false) {
        if ($periodIndex !== false) {
            $value = trim((string) ($row[$periodIndex] ?? ''));
            if ($value !== '') {
                $periods[$value] = true;
            }
        }
    }
    fclose($periodHandle);
}
$periods = array_values(array_keys($periods));
sort($periods);

$connection = config('database.default', 'mysql');
$dbConfig = config("database.connections.{$connection}", []);
$charset = $dbConfig['charset'] ?? 'utf8mb4';
$host = $dbConfig['host'] ?? '127.0.0.1';
$port = $dbConfig['port'] ?? '3306';
$database = $dbConfig['database'] ?? '';
$username = $dbConfig['username'] ?? '';
$password = $dbConfig['password'] ?? '';
$unixSocket = $dbConfig['unix_socket'] ?? '';

$dsn = $unixSocket !== ''
    ? "mysql:unix_socket={$unixSocket};dbname={$database};charset={$charset}"
    : "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_LOCAL_INFILE => true,
    PDO::ATTR_TIMEOUT => 120,
]);

$tableColumns = [
    'posisi',
    'regional_office',
    'kantor_cabang',
    'unit_kerja',
    'CIFNO',
    'no_rekening',
    'jenis_simpanan',
    'status',
    'saldo_idr',
];

$columnMap = [];
foreach ($headers as $index => $header) {
    $normalized = trim((string) $header);
    if ($normalized === 'cifno') {
        $columnMap[$index] = 'CIFNO';
        continue;
    }

    if (in_array($normalized, $tableColumns, true)) {
        $columnMap[$index] = $normalized;
    }
}

if ($columnMap === []) {
    @unlink($normalizedCsvPath);
    fail('Tidak ada kolom yang dapat dipetakan ke tabel simpanan_multipn.');
}

$fieldVariables = [];
$setClauses = [];
foreach ($headers as $index => $header) {
    $fieldVariables[] = '@col_' . $index;
}

$batchTimestamp = date('Y-m-d H:i:s');
$batchToken = 'SMPN_' . str_replace('-', '', (string) Illuminate\Support\Str::uuid()) . '_';
$setClauses[] = "`created_at` = '{$batchTimestamp}'";
$setClauses[] = "`updated_at` = '{$batchTimestamp}'";
$setClauses[] = "`uniqueid_SMPN` = CONCAT('{$batchToken}', REPLACE(UUID(), '-', ''), '_SMPN')";

foreach ($columnMap as $index => $column) {
    $var = '@col_' . $index;
    $setClauses[] = "`{$column}` = NULLIF(TRIM({$var}), '')";
}

$quotedPath = $pdo->quote(str_replace('\\', '/', realpath($normalizedCsvPath) ?: $normalizedCsvPath));
$fields = implode(', ', $fieldVariables);
$setClause = implode(",\n", $setClauses);

$loadSql = "LOAD DATA LOCAL INFILE {$quotedPath} INTO TABLE `simpanan_multipn` "
    . "CHARACTER SET utf8mb4 "
    . "FIELDS TERMINATED BY ';' OPTIONALLY ENCLOSED BY '\"' "
    . "LINES TERMINATED BY '\\n' "
    . "IGNORE 1 LINES "
    . "({$fields}) "
    . "SET {$setClause}";

$importStart = microtime(true);
$affected = 0;

try {
    $pdo->beginTransaction();
    $pdo->exec('SET @skip_snapshot_invalidation = 1');

    $affectedResult = $pdo->exec($loadSql);
    if ($affectedResult === false) {
        throw new RuntimeException('LOAD DATA LOCAL INFILE gagal.');
    }
    $affected = (int) $affectedResult;

    $summarySql = "SELECT COUNT(*) AS row_count, COALESCE(SUM(COALESCE(`saldo_idr`, 0)), 0) AS total_balance
        FROM `simpanan_multipn`
        WHERE `created_at` = " . $pdo->quote($batchTimestamp);
    $summary = $pdo->query($summarySql)?->fetch(PDO::FETCH_ASSOC) ?: [];

    $rowCount = (int) ($summary['row_count'] ?? 0);
    $balanceCents = decimalStringToCents((string) ($summary['total_balance'] ?? '0.00'));
    $expectedRows = (int) ($donePayload['written_rows'] ?? 0);
    $expectedBalanceCents = (int) ($donePayload['balance_total_cents'] ?? 0);

    if ($rowCount !== $expectedRows || $affected !== $expectedRows) {
        throw new RuntimeException("Crosscheck row count gagal. expected={$expectedRows}, load_data={$affected}, query={$rowCount}");
    }

    if ($balanceCents !== $expectedBalanceCents) {
        throw new RuntimeException(
            'Crosscheck saldo gagal. expected=' . formatCents($expectedBalanceCents)
            . ', actual=' . formatCents($balanceCents)
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    @unlink($normalizedCsvPath);
    fail($e->getMessage());
} finally {
    try {
        $pdo->exec('SET @skip_snapshot_invalidation = NULL');
    } catch (Throwable) {
    }
}

$importElapsed = microtime(true) - $importStart;

$sampleAccounts = [];
foreach ((array) ($donePayload['account_samples'] ?? []) as $sample) {
    if (!is_array($sample)) {
        continue;
    }
    $normalized = trim((string) ($sample['normalized'] ?? ''));
    if ($normalized !== '') {
        $sampleAccounts[$normalized] = [
            'raw' => (string) ($sample['raw'] ?? ''),
            'normalized' => $normalized,
        ];
    }
}

$verifiedSamples = [];
if ($sampleAccounts !== []) {
    $quotedAccounts = implode(', ', array_map([$pdo, 'quote'], array_keys($sampleAccounts)));
    $rows = $pdo->query(
        "SELECT `no_rekening`, `posisi`, `saldo_idr` FROM `simpanan_multipn`
         WHERE `created_at` = " . $pdo->quote($batchTimestamp) . "
           AND `no_rekening` IN ({$quotedAccounts})
         ORDER BY `no_rekening`
         LIMIT 20"
    )?->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $row) {
        $rekening = (string) ($row['no_rekening'] ?? '');
        $sample = $sampleAccounts[$rekening] ?? ['raw' => '', 'normalized' => $rekening];
        $verifiedSamples[] = [
            'raw' => $sample['raw'],
            'normalized' => $sample['normalized'],
            'db_no_rekening' => $rekening,
            'posisi' => (string) ($row['posisi'] ?? ''),
            'saldo_idr' => (string) ($row['saldo_idr'] ?? ''),
        ];
    }
}

echo json_encode([
    'source_path' => $sourcePath,
    'normalized_csv_path' => $normalizedCsvPath,
    'periods' => $periods,
    'source_total_rows' => (int) ($donePayload['total_rows'] ?? 0),
    'source_written_rows' => (int) ($donePayload['written_rows'] ?? 0),
    'source_skipped_rows' => (int) ($donePayload['skipped_count'] ?? 0),
    'source_duplicate_rows' => (int) ($donePayload['duplicate_count'] ?? 0),
    'source_balance_total_cents' => (int) ($donePayload['balance_total_cents'] ?? 0),
    'source_balance_total' => formatCents((int) ($donePayload['balance_total_cents'] ?? 0)),
    'stage_elapsed_seconds' => round($stageElapsed, 3),
    'import_elapsed_seconds' => round($importElapsed, 3),
    'import_rows_per_second' => $importElapsed > 0 ? (int) round(((int) ($donePayload['written_rows'] ?? 0)) / $importElapsed) : 0,
    'batch_timestamp' => $batchTimestamp,
    'inserted_rows' => $affected,
    'verified_samples' => $verifiedSamples,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
