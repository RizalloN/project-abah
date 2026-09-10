<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;

final class ConsumerRmKanwilAudit
{
    private const BRANCHES = ['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'];

    /** Read cached workbook values only; never recalculate external links. */
    public function referenceRows(string $path, string $period): array
    {
        $date = CarbonImmutable::parse($period);
        if ($date->year !== 2026 || $date->toDateString() !== $date->endOfMonth()->toDateString()) {
            throw new InvalidArgumentException('Acuan ini untuk posisi akhir bulan tahun 2026.');
        }
        if (! is_file($path)) {
            throw new InvalidArgumentException('Workbook acuan tidak ditemukan.');
        }

        $reader = (new Xlsx)->setReadDataOnly(false)->setLoadSheetsOnly('RM - Sort');
        $book = $reader->load($path);
        try {
            $sheet = $book->getSheetByName('RM - Sort');
            if ($sheet === null || $sheet->getCell('G3')->getValue() !== 'Nama RM') {
                throw new RuntimeException('Format acuan RM - Sort tidak dikenali.');
            }
            $read = static function (int $column, int $row) use ($sheet): mixed {
                $cell = $sheet->getCell([$column, $row]);

                return $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
            };
            $column = 13 + 12 * ($date->month - 1);
            $rows = [];
            for ($row = 6; $row <= $sheet->getHighestDataRow(); $row++) {
                $branch = strtoupper(trim((string) $read(5, $row)));
                if (! in_array($branch, self::BRANCHES, true)) {
                    continue;
                }
                $name = trim((string) $read(7, $row));
                $key = $this->identity($name);
                if ($key === '' || isset($rows[$key])) {
                    throw new RuntimeException("Identitas RM kosong/ambigu di baris {$row}.");
                }
                $entry = ['name' => $name, 'branch' => $branch, 'row' => $row];
                foreach (['new_count', 'new_amount', 'supp_count', 'supp_amount', 'count', 'amount'] as $offset => $field) {
                    $value = $read($column + $offset, $row);
                    if (! is_numeric($value)) {
                        throw new RuntimeException("Nilai acuan {$field} baris {$row} tidak tersedia/numerik.");
                    }
                    $entry[$field] = str_ends_with($field, 'count') ? (int) $value : (float) $value * 1000000;
                }
                $target = $read($column + 7, $row);
                if (! is_numeric($target) || (float) $target <= 0) {
                    throw new RuntimeException("Target acuan baris {$row} tidak valid.");
                }
                $entry['target'] = (float) $target * 1000000;
                $quadrant = $read($column + 11, $row);
                if (! preg_match('/^Kuadran ([1-4])$/', (string) $quadrant, $match)) {
                    throw new RuntimeException("Kuadran acuan baris {$row} tidak tersedia.");
                }
                $entry['quadrant'] = (int) $match[1];
                if ($entry['count'] !== $entry['new_count'] + $entry['supp_count']
                    || abs($entry['amount'] - $entry['new_amount'] - $entry['supp_amount']) > 0.01
                    || ConsumerKanwilReference::calculateQuadrant($entry['amount'], $entry['target']) !== $entry['quadrant']) {
                    throw new RuntimeException("Total/kuadran acuan baris {$row} tidak konsisten.");
                }
                $rows[$key] = $entry;
            }
            if ($rows === []) {
                throw new RuntimeException('Acuan tidak memuat RM Area 6.');
            }

            return $rows;
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function audit(string $path, string $period, float $maxErrorPercent = 1.0): array
    {
        if (! is_finite($maxErrorPercent) || $maxErrorPercent < 0 || $maxErrorPercent > 100) {
            throw new InvalidArgumentException('Batas error harus antara 0 dan 100 persen.');
        }
        $reference = $this->referenceRows($path, $period);
        $actual = [];
        foreach (app(ConsumerRmRealizationCalculator::class)->calculate($period) as $metric) {
            if ($metric['produk'] !== 'BRIGUNA-KONSUMER'
                || ! in_array(strtoupper(trim($metric['cabang'])), array_map(fn ($branch) => 'KC '.$branch, self::BRANCHES), true)) {
                continue;
            }
            $key = $this->identity($metric['rm']);
            foreach (['count' => 'realisasi_deb', 'amount' => 'realisasi_os', 'new_count' => 'realisasi_baru_deb',
                'new_amount' => 'realisasi_baru_os', 'supp_count' => 'suplesi_deb', 'supp_amount' => 'suplesi_os'] as $field => $source) {
                $actual[$key][$field] = ($actual[$key][$field] ?? 0) + $metric[$source];
            }
        }
        $snapshots = [];
        foreach (DB::table('performance_rm_snapshots')->where('periode', $period)
            ->where('segmen', 'CONSUMER')->where('produk', 'BRIGUNA-KONSUMER')
            ->whereIn('cabang', array_map(fn ($branch) => 'KC '.$branch, self::BRANCHES))
            ->get(['rm', 'realisasi_deb', 'realisasi_os']) as $snapshot) {
            $key = $this->identity($snapshot->rm);
            $snapshots[$key]['count'] = ($snapshots[$key]['count'] ?? 0) + (int) $snapshot->realisasi_deb;
            $snapshots[$key]['amount'] = ($snapshots[$key]['amount'] ?? 0) + (float) $snapshot->realisasi_os;
        }

        return $this->compare($reference, $actual, $snapshots, $maxErrorPercent) + [
            'period' => $period,
            'reference_sha256' => hash_file('sha256', $path),
            'reference_product' => 'BRIGUNA-KONSUMER',
            'kpr_reference_available' => false,
        ];
    }

    public function compare(array $reference, array $actual, array $snapshots, float $maxErrorPercent): array
    {
        $rows = [];
        $absoluteError = 0.0;
        $referenceTotal = 0.0;
        foreach ($reference as $key => $expected) {
            $observed = $actual[$key] ?? [];
            $row = ['name' => $expected['name'], 'branch' => $expected['branch'], 'target' => $expected['target']];
            $passed = true;
            foreach (['count', 'amount', 'new_count', 'new_amount', 'supp_count', 'supp_amount'] as $field) {
                $value = $observed[$field] ?? 0;
                $row[$field] = $value;
                $row['reference_'.$field] = $expected[$field];
                $row['delta_'.$field] = $value - $expected[$field];
                $percent = $this->errorPercent($value, $expected[$field]);
                $row['error_percent_'.$field] = $percent;
                $passed = $passed && (str_ends_with($field, 'count')
                    ? (int) $value === (int) $expected[$field]
                    : ($percent !== null && $percent <= $maxErrorPercent));
            }
            $row['quadrant'] = ConsumerKanwilReference::calculateQuadrant($row['amount'], $expected['target']);
            $row['reference_quadrant'] = $expected['quadrant'];
            $row['quadrant_match'] = $row['quadrant'] === $expected['quadrant'];
            $row['snapshot_amount'] = $snapshots[$key]['amount'] ?? null;
            $row['snapshot_match'] = isset($snapshots[$key])
                && abs($snapshots[$key]['amount'] - $row['amount']) < 0.01
                && (int) $snapshots[$key]['count'] === (int) $row['count'];
            $row['passed'] = $passed && $row['quadrant_match'] && $row['snapshot_match'];
            $absoluteError += abs($row['delta_amount']);
            $referenceTotal += abs($expected['amount']);
            $rows[] = $row;
        }
        $unmatched = array_keys(array_filter(array_diff_key($actual, $reference),
            fn ($row) => ($row['count'] ?? 0) > 0 || abs($row['amount'] ?? 0) > 0.01));

        return [
            'passed' => $rows !== [] && $unmatched === [] && ! in_array(false, array_column($rows, 'passed'), true),
            'max_error_percent' => $maxErrorPercent,
            'rm_count' => count($rows),
            'quadrant_matches' => count(array_filter(array_column($rows, 'quadrant_match'))),
            'absolute_error' => $absoluteError,
            'reference_total' => $referenceTotal,
            'weighted_absolute_error_percent' => $this->errorPercent($referenceTotal + $absoluteError, $referenceTotal),
            'unmatched_realization_identities' => $unmatched,
            'rows' => $rows,
        ];
    }

    private function errorPercent(float $actual, float $reference): ?float
    {
        if (abs($reference) < 0.005) {
            return abs($actual) < 0.005 ? 0.0 : null;
        }

        return abs($actual - $reference) / abs($reference) * 100;
    }

    private function identity(string $rm): string
    {
        $known = ConsumerKanwilReference::findRm($rm);
        $name = $known['nama'] ?? preg_replace('/^\s*\d+\s*-\s*/', '', $rm);

        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($name)));
    }
}
