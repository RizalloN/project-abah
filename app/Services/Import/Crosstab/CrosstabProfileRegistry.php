<?php

namespace App\Services\Import\Crosstab;

use App\Services\Import\CrasSourceService;
use App\Services\Import\Strategies\SsaPinjamanImportStrategy;
use App\Services\Import\Strategies\SsaSimpananImportStrategy;

/**
 * Reports whose Excel exports arrive as crosstabs (merged or blank-repeated labels).
 *
 * The SSA reports are built in. Any other report can opt in through
 * config('import.crosstab_profiles.<table_name>') without code changes.
 */
class CrosstabProfileRegistry
{
    public function for(string $tableName): ?CrosstabProfile
    {
        $table = strtolower(trim($tableName));

        return match ($table) {
            'ssa_simpanan' => $this->ssaSimpanan(),
            'ssa_pinjaman' => $this->ssaPinjaman(),
            'ssa_almafacts' => $this->ssaAlmafacts(),
            CrasSourceService::TABLE => $this->cras(),
            default => $this->fromConfig($table),
        };
    }

    private function ssaSimpanan(): CrosstabProfile
    {
        $strategy = new SsaSimpananImportStrategy;

        return new CrosstabProfile(
            key: 'ssa_simpanan',
            label: 'SSA',
            columns: [
                'month_day_year_of_posisi', 'nama_cabang', 'nama_uker', 'produk',
                'segmentasi', 'segmen_kategorisasi_bisnis', 'saldo',
            ],
            dimensionCount: 6,
            aliases: ['nominal' => 'saldo', 'saldo_akhir' => 'saldo', 'posisi' => 'month_day_year_of_posisi'],
            headerResolver: static fn (string $label): string => $strategy->transformHeaders([$label])[0],
        );
    }

    private function ssaPinjaman(): CrosstabProfile
    {
        $strategy = new SsaPinjamanImportStrategy;

        return new CrosstabProfile(
            key: 'ssa_pinjaman',
            label: 'SSA',
            columns: [
                'month_day_year_of_periode', 'nama_cabang', 'nama_uker', 'produk',
                'produk_dashboard', 'segmen', 'segmen_lama', 'segmen_2025',
                'segmen_dashboard', 'kolektabilitas_one_obligor', 'flag_restruk',
                'baki_debet', 'jumlah_debitur_aktif', 'jumlah_rekening_aktif',
            ],
            dimensionCount: 11,
            aliases: ['periode' => 'month_day_year_of_periode', 'os' => 'baki_debet', 'outstanding' => 'baki_debet'],
            integerColumns: ['kolektabilitas_one_obligor', 'jumlah_debitur_aktif', 'jumlah_rekening_aktif'],
            numericDimensions: ['kolektabilitas_one_obligor'],
            headerResolver: static fn (string $label): string => $strategy->transformHeaders([$label])[0],
        );
    }

    private function ssaAlmafacts(): CrosstabProfile
    {
        return new CrosstabProfile(
            key: 'ssa_almafacts',
            label: 'SSA Almafacts',
            columns: ['month_day_year_of_posisi', 'kanca_konsolidasi', 'kode_unit_kerja', 'unit_kerja', 'keterangan', 'saldo'],
            dimensionCount: 5,
            aliases: [
                'posisi' => 'month_day_year_of_posisi',
                'tanggal_posisi' => 'month_day_year_of_posisi',
                'kanca' => 'kanca_konsolidasi',
                'nama_kanca' => 'kanca_konsolidasi',
                'kode_uker' => 'kode_unit_kerja',
                'kode_unit' => 'kode_unit_kerja',
                'uker' => 'unit_kerja',
                'nama_uker' => 'unit_kerja',
                'nama_unit_kerja' => 'unit_kerja',
                'ket' => 'keterangan',
                'nilai' => 'saldo',
                'nominal' => 'saldo',
            ],
            // The same labels as Tableau's own CSV export, which the Almafacts import reads by position.
            outputHeaders: [
                'Month, Day, Year of 00. Posisi', '03. Kanca Konsolidasi', '05. Kode Unit Kerja',
                '04. Unit Kerja', 'Keterangan', 'Saldo',
            ],
            valueMode: CrosstabProfile::VALUES_SOURCE,
            dateFormat: 'F j, Y',
            rejectBlankDimensions: false,
            rejectBlankMeasures: false,
        );
    }

    private function cras(): CrosstabProfile
    {
        $aliases = [];
        foreach (CrasSourceService::SOURCE_HEADERS as $index => $header) {
            $aliases[SpreadsheetCrosstabNormalizer::normalizeLabel($header)] = CrasSourceService::SOURCE_COLUMNS[$index];
        }

        return new CrosstabProfile(
            key: CrasSourceService::TABLE,
            label: 'SSA CRAS',
            columns: CrasSourceService::SOURCE_COLUMNS,
            // Posisi .. Flag Restruk group the rows; Accint onwards are measures.
            dimensionCount: 18,
            aliases: $aliases + [
                'br_number' => 'br_number',
                'kode_uker' => 'br_number',
                'kanca' => 'ket_kanca',
                'nama_cabang' => 'ket_kanca',
                'unit_kerja' => 'ket_unit_kerja',
                'nama_uker' => 'ket_unit_kerja',
            ],
            outputHeaders: CrasSourceService::SOURCE_HEADERS,
            // CrasSourceService reads UTF-8 tab-delimited text with the template headers.
            delimiter: "\t",
            extension: 'txt',
            valueMode: CrosstabProfile::VALUES_SOURCE,
            dateFormat: 'F j, Y',
            rejectBlankDimensions: false,
            rejectBlankMeasures: false,
            passthroughNeedsExactHeaders: true,
        );
    }

    private function fromConfig(string $table): ?CrosstabProfile
    {
        $config = config("import.crosstab_profiles.{$table}");
        if (!is_array($config) || empty($config['columns']) || !isset($config['dimension_count'])) {
            return null;
        }

        $aliases = [];
        foreach ((array) ($config['aliases'] ?? []) as $label => $column) {
            $aliases[SpreadsheetCrosstabNormalizer::normalizeLabel((string) $label)] = (string) $column;
        }

        return new CrosstabProfile(
            key: $table,
            label: (string) ($config['label'] ?? $table),
            columns: array_values(array_map('strval', (array) $config['columns'])),
            dimensionCount: (int) $config['dimension_count'],
            aliases: $aliases,
            outputHeaders: isset($config['output_headers']) ? array_values((array) $config['output_headers']) : null,
            valueMode: ($config['value_mode'] ?? CrosstabProfile::VALUES_SOURCE) === CrosstabProfile::VALUES_VALIDATED
                ? CrosstabProfile::VALUES_VALIDATED
                : CrosstabProfile::VALUES_SOURCE,
            dateFormat: (string) ($config['date_format'] ?? 'Y-m-d'),
            rejectBlankDimensions: (bool) ($config['reject_blank_dimensions'] ?? false),
            rejectBlankMeasures: (bool) ($config['reject_blank_measures'] ?? false),
            integerColumns: array_values((array) ($config['integer_columns'] ?? [])),
        );
    }
}
