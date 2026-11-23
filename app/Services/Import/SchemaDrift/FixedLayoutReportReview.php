<?php

namespace App\Services\Import\SchemaDrift;

use App\Services\Import\DlyKapResegmentasiCsvImporter;
use App\Services\Import\L1133CsvImporter;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class FixedLayoutReportReview
{
    public function __construct(private readonly ImportSourceSampler $sampler) {}

    public function sample(string $table, string $path, array $columns, array $options): ?array
    {
        $headers = match ($table) {
            DlyKapResegmentasiCsvImporter::TABLE => DlyKapResegmentasiCsvImporter::NORMALIZED_HEADERS,
            L1133CsvImporter::TABLE => L1133CsvImporter::NORMALIZED_HEADERS,
            default => [],
        };
        if ($headers === [] || $this->hasNormalizedHeaders($path, $headers)) {
            return null;
        }

        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new RuntimeException('Identitas file sumber tidak dapat dibaca.');
        }
        $cacheKey = 'schema-review:fixed-layout:v1:'.hash('sha256', json_encode([$table, $hash, $columns]));

        return Cache::remember($cacheKey, now()->addHours(2), function () use ($table, $path, $headers, $columns, $options, $hash): array {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'abah-schema-');
            $file = $temporaryPath !== false ? fopen($temporaryPath, 'wb') : false;
            if ($file === false) {
                if ($temporaryPath !== false) {
                    unlink($temporaryPath);
                }
                throw new RuntimeException('File sementara untuk pemeriksaan struktur gagal dibuat.');
            }

            try {
                fputcsv($file, $headers, ',', '"', '');
                $write = static function (array $row) use ($file, $headers): bool {
                    fputcsv($file, array_map(static fn (string $column) => $row[$column] ?? null, $headers), ',', '"', '');

                    return true;
                };
                if ($table === DlyKapResegmentasiCsvImporter::TABLE) {
                    $source = app(DlyKapResegmentasiCsvImporter::class)->parse($path, inspectStructure: true);
                    foreach ($source['rows'] as $row) {
                        $write($row);
                    }
                    unset($source['rows']);
                } else {
                    $warnings = [];
                    $source = app(L1133CsvImporter::class)->streamNormalizedRows($path, $write, $warnings, inspectStructure: true);
                    $source['warnings'] = $warnings;
                }
                fflush($file);
                $sample = $this->sampler->sample($temporaryPath, static fn (array $labels): array => $labels, $columns, [
                    'delimiter' => ',', 'header_index' => 0,
                ] + $options);
                if (!hash_equals($hash, (string) hash_file('sha256', $path))) {
                    throw new RuntimeException('File sumber berubah saat dipindai. Pindai ulang preview.');
                }

                $sample['format'] = 'fixed_layout';
                $sample['delimiter'] = null;
                $sample['structural_scan']['source_fingerprint'] = $hash;
                $sample['source_issues'] = $this->sourceIssues($source);

                return $sample;
            } finally {
                fclose($file);
                unlink($temporaryPath);
            }
        });
    }

    private function hasNormalizedHeaders(string $path, array $expected): bool
    {
        if (!is_file($path)) {
            throw new RuntimeException('File sumber tidak ditemukan atau tidak dapat dibaca.');
        }
        if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
            return false;
        }
        $file = fopen($path, 'rb');
        if ($file === false) {
            throw new RuntimeException('File sumber tidak dapat dibuka.');
        }
        try {
            $row = fgetcsv($file, 0, ',', '"', '');
            if (is_array($row)) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($row[0] ?? ''));
            }

            return is_array($row) && array_map(static fn ($header) => strtolower(trim((string) $header)), $row) === $expected;
        } finally {
            fclose($file);
        }
    }

    private function sourceIssues(array $source): array
    {
        $issues = [];
        $extras = array_values(array_unique($source['extra_headers'] ?? []));
        if ($extras !== []) {
            $issues[] = [
                'key' => 'fixed_layout_unmapped:'.sha1(json_encode($extras)),
                'title' => count($extras).' nama kolom sumber di luar mapping report',
                'detail' => 'Nilai dari kolom berikut akan dilewati jika Anda melanjutkan: '.implode(', ', $extras).'.',
                'blocking' => false,
            ];
        }
        if (($source['source_layout'] ?? []) !== []) {
            $issues[] = [
                'key' => 'fixed_layout_positions:'.sha1(json_encode($source['source_layout'])),
                'title' => 'Mapping DLY KAP berdasarkan urutan kolom',
                'detail' => 'DLY KAP membaca 16 kolom pertama dari tiap bagian SEGMEN. Cocokkan urutan kolom dengan dokumen sumber sebelum melanjutkan.',
                'blocking' => false,
            ];
        }
        if (($source['warnings'] ?? []) !== []) {
            $issues[] = [
                'key' => 'fixed_layout_invalid_rows',
                'title' => 'Baris atau metadata sumber tidak lengkap',
                'detail' => implode(' ', array_slice($source['warnings'], 0, 4)),
                'blocking' => true,
            ];
        }

        return $issues;
    }
}
