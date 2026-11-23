<?php

namespace App\Services\Import\SchemaDrift;

use App\Http\Controllers\Import\ImportCognosRecoveryController;
use App\Http\Controllers\Import\ImportFileBrimoController;
use App\Http\Controllers\Import\ImportFileController;
use App\Services\Import\ImportStrategyFactory;
use App\Services\Import\MauBrimoImportPolicy;
use App\Services\Import\Strategies\Gi405RecDhImportStrategy;
use App\Services\Import\SchemaIntrospectionService;
use App\Services\Import\Strategies\Lw325PhImportStrategy;
use App\Services\Import\Strategies\PerformancePisImportStrategy;
use App\Support\Import\ImportHeaderAliases;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One entry point for "is this source still the shape we expect?":
 * sample the file → detect drift against the table → remember formats →
 * record a notification when a decision or acknowledgement is needed.
 */
class SchemaDriftAnalyzer
{
    /**
     * Columns the import pipeline fills itself; they are never "missing" and a
     * source column with the same name is not treated as data.
     */
    private const MANAGED_COLUMNS = [
        'monitoring_realisasi_ksbk' => ['uniqueid_namareport', 'cek_real', 'plafond', 'ln_type', 'ksbk_non'],
        'cras' => ['cras_uuid', 'cras_periode'],
        'input_rekanan' => ['periode'],
        'bod_boc' => ['periode'],
        'gi405_recovery' => ['uniqueid_namareport', 'nama_uker', 'source_periode'],
        'lw325_ph' => ['uniqueid_namareport'],
        'daily_loan_dinamis' => [
            'uniqueid_namareport', 'segmen_kinerja', 'produk_kinerja', 'cabang_normalized', 'unit_normalized',
            'branch_normalized', 'rm_normalized', 'pn_pemutus_normalized', 'cifno_clean', 'shadow_built_at',
            'cabang_normalized_gc', 'unit_normalized_gc', 'branch_normalized_gc', 'rm_normalized_gc',
            'pn_pemutus_normalized_gc', 'cifno_clean_gc',
        ],
    ];

    public function __construct(
        private readonly ImportSourceSampler $sampler,
        private readonly SchemaDriftDetector $detector,
        private readonly SchemaDriftRegistry $registry,
        private readonly SchemaIntrospectionService $schema,
        private readonly ImportStrategyFactory $strategies,
        private readonly SourceHeaderBaseline $baseline,
    ) {
    }

    /**
     * @param array{
     *     report_label?: string|null,
     *     delimiter?: string|null,
     *     header_index?: int|null,
     *     sample_rows?: int,
     *     record?: bool,
     *     remember?: bool,
     *     source_name?: string|null
     * } $options
     */
    public function analyze(string $table, string $absolutePath, array $options = []): array
    {
        $table = strtolower(trim($table));
        $startedAt = microtime(true);
        $canonicalize = $this->canonicalizer($table);
        $columnMetadata = $this->schema->refreshColumnMetadata($table);
        $tableColumnsLower = array_keys($columnMetadata);
        $isBrilinkSummary = $table === ImportFileController::BRILINK_SUMMARY_TABLE;
        if ($isBrilinkSummary) {
            $options += SchemaDriftImportGuard::delimitedOptions('csv', null);
        }
        $isCognosRecovery = $table === 'cognos_recovery';
        if ($isCognosRecovery && in_array(strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
            $options += SchemaDriftImportGuard::delimitedOptions('cognos-recovery', ';');
        }

        $sampleOptions = $options + ['target_table' => $table];
        $sample = app(FixedLayoutReportReview::class)->sample($table, $absolutePath, $tableColumnsLower, $sampleOptions)
            ?? $this->sampler->sample($absolutePath, $canonicalize, $tableColumnsLower, $sampleOptions);
        // Header discovery must inspect source labels. Apply the import's
        // positional layout only after discovery so data is never a header.
        if ($table === 'ssa_almafacts') {
            $sample['canonical_headers'] = ImportHeaderAliases::ssaAlmafactsHeaders($sample['canonical_headers']);
        }

        // Without the baseline table (migration not yet run) the detector keeps
        // its original strict behaviour instead of silently accepting headers.
        $baselineAvailable = $this->baseline->isAvailable();

        $sourceFingerprint = (string) ($sample['structural_scan']['source_fingerprint'] ?? '');
        $columnAliases = $this->registry->columnAliases($table);
        $brilinkOptions = $isBrilinkSummary ? $this->brilinkSummaryOptions($sample, $tableColumnsLower) : [];
        $reportOptions = $isCognosRecovery ? $this->cognosRecoveryOptions($sample, $tableColumnsLower) : $brilinkOptions;
        $report = $this->detector->detect($table, $sample['headers'], $sample['rows'], $reportOptions + [
            'column_metadata' => $columnMetadata,
            'source_targets' => $this->sourceTargets($table, $sample['headers'], $tableColumnsLower, $columnAliases, $options['mapping'] ?? null),
            // MAU enforces the data types itself for eligible Area 6 rows
            // and parses large integers exactly in its dedicated writer.
            'check_value_types' => $table !== MauBrimoImportPolicy::TABLE,
            'canonical_headers' => $sample['canonical_headers'],
            'ignored_headers' => $this->ignoredHeaders($table),
            'managed_columns' => self::MANAGED_COLUMNS[$table] ?? ['uniqueid_namareport'],
            'rejected_columns' => $this->registry->rejectedColumns($table, $sourceFingerprint),
            'known_headers' => $baselineAvailable ? $this->baseline->knownKeys($table) : [],
            'baseline_established' => !$baselineAvailable || $this->baseline->isEstablished($table),
            'previous_document' => $baselineAvailable ? $this->baseline->previousDocument($table) : [],
            'column_aliases' => $columnAliases,
            'profile_values' => $sample['profile_values'] ?? [],
            'structural_scan' => $sample['structural_scan'] ?? [],
            'source_issues' => $sample['source_issues'] ?? [],
            'approved_review_keys' => $this->registry->approvedReviewKeys($table, (string) ($sample['structural_scan']['source_fingerprint'] ?? '')),
            'report_label' => $options['report_label'] ?? $this->reportLabel($table),
        ]);

        // A fixed-layout pipeline cannot route a renamed header onto an existing
        // column, so the rename is reported for review instead of offered as a
        // one-click mapping. Widening a column helps every pipeline.
        $mode = app(SchemaEvolutionPolicy::class)->modeFor($table);
        $report['evolution_mode'] = $mode;
        if ($mode === SchemaEvolutionPolicy::MODE_MONITOR) {
            foreach ($report['new_columns'] as &$newColumn) {
                if (($newColumn['suggestion']['action'] ?? null) === 'map') {
                    $newColumn['suggestion']['action'] = 'review';
                }
            }
            unset($newColumn);
        }

        $report['source'] = [
            'name' => $options['source_name'] ?? basename($absolutePath),
            'format' => $sample['format'],
            'delimiter' => $sample['delimiter'],
            'header_row' => $sample['format'] === 'fixed_layout' ? null : $sample['header_index'] + 1,
            'metadata' => $sample['metadata'],
            'misaligned_rows' => $sample['misaligned_rows'],
            'structural_scan' => $sample['structural_scan'] ?? [],
        ];
        $report['source_fingerprint'] = (string) ($sample['structural_scan']['source_fingerprint'] ?? '');
        if ($isBrilinkSummary && empty($report['has_blocking_issue'])) {
            $report['layout_ignored_headers'] = $brilinkOptions['ignored_headers'];
        }
        $report['analyzed_in_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        $event = null;
        if (($options['record'] ?? true) === true) {
            try {
                $event = $this->registry->record($report, $report['source']['name']);
                $report['resolved_events'] = $this->registry->reconcile($report, $event ? (int) $event->id : null);
            } catch (Throwable $e) {
                Log::warning('Schema drift event could not be recorded.', ['table' => $table, 'error' => $e->getMessage()]);
            }
        }

        if (($options['remember'] ?? true) === true) {
            // Unreviewed source content must not replace the last accepted
            // format/layout reference or erase evidence of a change.
            if (!$report['has_pending_decision'] && !$report['has_blocking_issue']) {
                $this->detector->rememberFormats($report);
            }
            if ($baselineAvailable && !$report['has_pending_decision'] && !$report['has_blocking_issue']) {
                $this->baseline->record($table, (array) $report['columns']);
            }
        }

        $report['event'] = $event ? $this->registry->presentNotification($event) : null;
        $report['columns'] = array_map(static function (array $column): array {
            unset($column['profile_full']);

            return $column;
        }, $report['columns']);

        return $report;
    }

    /**
     * @return callable(array<int, string>): array<int, string>
     */
    public function canonicalizer(string $table): callable
    {
        $strategy = null;
        try {
            $strategy = $this->strategies->resolve(null, $table);
        } catch (Throwable) {
            $strategy = null;
        }

        return static function (array $headers) use ($strategy): array {
            $headers = array_values($headers);
            $transformed = $strategy !== null ? array_values($strategy->transformHeaders($headers)) : $headers;

            return array_map(static function ($header, $index) use ($headers): string {
                $value = trim((string) $header);
                if ($value === '' || preg_match('/^col_\d+$/i', $value) === 1) {
                    return trim((string) ($headers[$index] ?? '')) === '' ? 'col_' . $index : strtolower($value);
                }

                return trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $value)), '_');
            }, $transformed, array_keys($transformed));
        };
    }

    private function ignoredHeaders(string $table): array
    {
        $ignored = ['textbox3', 'no', 'no_urut', 'nomor_urut', 'row_num', 'rownumber', 'nomor_baris'];
        if ($table === 'lw325_ph') {
            $ignored = array_merge($ignored, Lw325PhImportStrategy::ROW_NUMBER_HEADERS);
        }
        if ($table === 'performance_pis_per_produk') {
            $ignored[] = 'posisi';
        }

        return array_values(array_unique($ignored));
    }

    private function brilinkSummaryOptions(array $sample, array $columns): array
    {
        $targets = app(ImportFileController::class)->brilinkSummarySourceTargets($sample['headers']);
        $scan = $sample['structural_scan'];
        $numbered = count($sample['headers']) === 16;
        $expectedNumberedRows = $numbered ? $scan['total_rows'] - $scan['misaligned_rows'] : 0;
        $ignored = [];
        $error = null;
        if ($sample['format'] !== 'delimited' || $targets === null) {
            $error = 'Gunakan ekspor CSV BRILink Web Summary dengan urutan header asli. Importer membaca kolom berdasarkan posisi.';
        } elseif ($scan['numbered_rows'] !== $expectedNumberedRows) {
            $error = 'Isi kolom kedua tidak konsisten dengan format nomor urut BRILink. Perbaiki baris sumber agar kolom transaksi tidak bergeser.';
        }

        if ($targets !== null) {
            $missing = array_diff(array_filter($targets), $columns);
            if ($missing !== []) {
                $error ??= 'Kolom tujuan BRILink belum tersedia di tabel: '.implode(', ', $missing).'.';
            }
            foreach ($targets as $index => $target) {
                if ($target === null) {
                    $ignored[] = $sample['canonical_headers'][$index];
                }
            }
        }

        $profiles = $sample['profile_values'];
        // The importer strips the vendor's "Periode :" prefix before writing.
        $profiles[0] = array_map(ImportFileController::brilinkSummaryPeriod(...), $profiles[0] ?? []);

        return [
            'source_targets' => array_intersect($targets ?? [], $columns),
            'ignored_headers' => $ignored,
            'profile_values' => $profiles,
            'source_layout_error' => $error,
        ];
    }

    private function sourceTargets(string $table, array $headers, array $columns, array $aliases, ?string $mapping): ?array
    {
        if (ReportTemplateSource::columns($table) !== []) {
            return array_intersect(ReportTemplateSource::sourceTargets($table, $headers), $columns);
        }
        if ($table === 'performance_pis_per_produk') {
            $writable = array_merge(PerformancePisImportStrategy::TARGET_COLUMNS, $this->registry->evolvedColumns($table), array_values($aliases));

            return PerformancePisImportStrategy::sourceTargets($headers, array_intersect($columns, $writable), $aliases);
        }
        if ($table === MauBrimoImportPolicy::TABLE) {
            return array_intersect(app(MauBrimoImportPolicy::class)->sourceTargets($headers), $columns);
        }
        if ($table === 'gi405_recovery') {
            $targets = array_map(static fn ($header): ?string => Gi405RecDhImportStrategy::SOURCE_TARGETS[strtolower(trim($header))] ?? null, $headers);

            return array_intersect($targets, $columns);
        }
        if ($mapping === 'brimo') {
            $controller = app(ImportFileBrimoController::class);

            return array_intersect(array_map(fn ($header) => $controller->schemaDriftSourceColumn((string) $header, $aliases), $headers), $columns);
        }
        if ($mapping === 'csv') {
            $controller = app(ImportFileController::class);

            return array_intersect(array_map(fn ($header, $index) => $controller->schemaDriftSourceColumn($table, $index, (string) $header, $aliases), $headers, array_keys($headers)), $columns);
        }

        return null;
    }

    private function cognosRecoveryOptions(array $sample, array $columns): array
    {
        $controller = app(ImportCognosRecoveryController::class);
        $targets = $controller->schemaDriftSourceTargets($sample['headers']);
        $periodIndex = array_search('periode', $targets, true);
        $profiles = $sample['profile_values'];
        if ($periodIndex !== false) {
            $profiles[$periodIndex] = array_map(
                static fn ($value) => $controller->normalizePeriodeValue((string) $value) ?? $value,
                $profiles[$periodIndex] ?? [],
            );
        }

        return [
            'source_targets' => array_intersect($targets, $columns),
            'canonical_headers' => array_replace($sample['canonical_headers'], $targets),
            'profile_values' => $profiles,
        ];
    }

    private function reportLabel(string $table): ?string
    {
        try {
            if (Schema::hasTable('nama_report')) {
                $label = DB::table('nama_report')->whereRaw('LOWER(TRIM(table_name)) = ?', [$table])->value('nama_report');

                return $label !== null ? (string) $label : null;
            }
        } catch (Throwable) {
            // Label is cosmetic.
        }

        return null;
    }
}
