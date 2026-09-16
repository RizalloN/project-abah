<?php

declare(strict_types=1);

use App\Support\LandingPnMismatchService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$period = $argv[1] ?? '2026-09-12';
if (DateTimeImmutable::createFromFormat('!Y-m-d', $period)?->format('Y-m-d') !== $period) {
    throw new InvalidArgumentException('Periode harus berformat YYYY-MM-DD.');
}

$report = $app->make(LandingPnMismatchService::class)->summary('sme', $period, null);
if (! $report['available']) {
    throw new RuntimeException('Data Daily Loan atau referensi BRIHC untuk periode ini tidak tersedia.');
}
if ($period === '2026-09-12' && $report['total'] !== 140) {
    throw new RuntimeException('Jumlah anomali berbeda dari tangkapan layar (140); ekspor dibatalkan.');
}

$rows = [];
$branchCounts = [];
foreach ($report['branches'] as $branch) {
    $branchName = (string) $branch['branch'];
    $managers = collect($branch['managers'])->keyBy('raw');
    $accounts = DB::table('daily_loan_dinamis')
        ->where('periode', $period)
        ->whereIn('segmen_dashboard', ['Small', 'SMALL'])
        ->whereRaw('UPPER(TRIM(cabang1)) = ?', [$branchName])
        ->whereIn(DB::raw('TRIM(pn_pengelola1)'), $managers->keys()->all())
        ->orderBy('nomor_rekening1')
        ->get(['nomor_rekening1', 'nama_debitur1', 'unit1', 'produk_dashboard', 'pn_pengelola1']);

    $branchCounts[$branchName] = $accounts->count();
    if ($branchCounts[$branchName] !== (int) $branch['count']) {
        throw new RuntimeException("Jumlah nominatif {$branchName} tidak sama dengan ringkasan; ekspor dibatalkan.");
    }

    foreach ($accounts as $account) {
        $raw = trim((string) $account->pn_pengelola1);
        $manager = $managers->get($raw);
        if (! is_array($manager)) {
            throw new RuntimeException('Identitas PN sumber tidak ditemukan di ringkasan; ekspor dibatalkan.');
        }
        $brihcName = (string) ($manager['brihc_name'] ?? '');
        $reason = ($manager['pn'] ?? '') === ''
            ? 'Format PN tidak sesuai'
            : ($brihcName === '' ? 'PN tidak ditemukan di BRIHC' : 'Nama PN berbeda dari BRIHC');
        $rows[] = [
            $branchName,
            (string) $account->nomor_rekening1,
            (string) $account->nama_debitur1,
            (string) $account->unit1,
            (string) $account->produk_dashboard,
            $raw,
            (string) ($manager['pn'] ?? ''),
            (string) ($manager['source_name'] ?? ''),
            $brihcName !== '' ? $brihcName : 'Tidak ditemukan di BRIHC',
            $reason,
        ];
    }
}

if (count($rows) !== (int) $report['total']) {
    throw new RuntimeException('Jumlah baris Excel tidak sama dengan ringkasan; ekspor dibatalkan.');
}

$book = new Spreadsheet();
$summary = $book->getActiveSheet();
$summary->setTitle('Ringkasan');
$summary->mergeCells('A1:D1');
$summary->setCellValue('A1', 'PN Tidak Sesuai BRIHC - SME');
$summary->setCellValue('A2', 'Posisi Daily Loan');
$summary->setCellValue('B2', $period);
$summary->setCellValue('A3', 'Total rekening');
$summary->setCellValue('B3', count($rows));
$summary->setCellValue('A4', 'Referensi nama');
$summary->setCellValue('B4', 'BRIHC saat laporan dibuat');
$summary->setCellValue('A6', 'Branch Office');
$summary->setCellValue('B6', 'Rekening tidak sesuai');
$line = 7;
foreach ($branchCounts as $branchName => $count) {
    $summary->setCellValue("A{$line}", $branchName);
    $summary->setCellValue("B{$line}", $count);
    $line++;
}
$summary->setCellValue("A{$line}", 'TOTAL');
$summary->setCellValue("B{$line}", count($rows));
$summary->getColumnDimension('A')->setWidth(25);
$summary->getColumnDimension('B')->setWidth(34);
$summary->getStyle('A1:D1')->getFont()->setBold(true)->setSize(15);
$summary->getStyle('A6:B6')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$summary->getStyle('A6:B6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0754BD');
$summary->getStyle("A{$line}:B{$line}")->getFont()->setBold(true);

$sheet = $book->createSheet();
$sheet->setTitle('Nominatif');
$sheet->mergeCells('A1:K1');
$sheet->setCellValue('A1', 'Nominatif PN Tidak Sesuai BRIHC - SME');
$sheet->setCellValue('A2', 'Posisi '.$period.' | Daily Loan Dinamis dibandingkan dengan BRIHC saat laporan dibuat');
$headers = ['No', 'Branch Office', 'Nomor Rekening', 'Nama Debitur', 'Unit Kerja', 'Produk',
    'PN Pengelola pada Daily Loan', 'PN Normalisasi', 'Nama pada Daily Loan', 'Nama BRIHC', 'Keterangan'];
foreach ($headers as $index => $header) {
    $sheet->setCellValue([$index + 1, 4], $header);
}
foreach ($rows as $index => $values) {
    $excelRow = $index + 5;
    $sheet->setCellValue([1, $excelRow], $index + 1);
    foreach ($values as $column => $value) {
        $sheet->setCellValueExplicit([$column + 2, $excelRow], $value, DataType::TYPE_STRING);
    }
}
$lastRow = count($rows) + 4;
$sheet->getStyle('A1:K1')->getFont()->setBold(true)->setSize(15);
$sheet->getStyle('A4:K4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A4:K4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0754BD');
$sheet->getStyle('A4:K4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(4)->setRowHeight(28);
$sheet->getColumnDimension('A')->setWidth(7);
foreach (['B' => 20, 'C' => 23, 'D' => 32, 'E' => 28, 'F' => 22,
    'G' => 34, 'H' => 18, 'I' => 30, 'J' => 30, 'K' => 31] as $column => $width) {
    $sheet->getColumnDimension($column)->setWidth($width);
}
$sheet->freezePane('A5');
$sheet->setAutoFilter("A4:K{$lastRow}");

$directory = storage_path('app/reports');
if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
    throw new RuntimeException('Direktori laporan tidak dapat dibuat.');
}
$path = $directory."/PN_Tidak_Sesuai_BRIHC_SME_{$period}.xlsx";
if (file_exists($path)) {
    throw new RuntimeException("File tujuan sudah ada, tidak ditimpa: {$path}");
}
(new Xlsx($book))->save($path);
$book->disconnectWorksheets();

echo json_encode(['path' => $path, 'period' => $period, 'total' => count($rows), 'branches' => $branchCounts], JSON_UNESCAPED_SLASHES).PHP_EOL;
