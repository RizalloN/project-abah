<?php

namespace App\Services\Import\SchemaDrift;

use App\Services\Import\ImportStrategyFactory;
use App\Services\Import\SchemaIntrospectionService;
use App\Services\Import\Strategies\Lw325PhImportStrategy;
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
        $tableColumnsLower = array_keys($this->schema->getColumnMetadata($table));

        $sample = $this->sampler->sample($absolutePath, $canonicalize, $tableColumnsLower, $options);

        // Without the baseline table (migration not yet run) the detector keeps
        // its original strict behaviour instead of silently accepting headers.
        $baselineAvailable = $this->baseline->isAvailable();

        $report = $this->detector->detect($table, $sample['headers'], $sample['rows'], [
            'canonical_headers' => $sample['canonical_headers'],
            'ignored_headers' => $this->ignoredHeaders($table),
            'managed_columns' => self::MANAGED_COLUMNS[$table] ?? ['uniqueid_namareport'],
            'rejected_columns' => $this->registry->rejectedColumns($table),
            'known_headers' => $baselineAvailable ? $this->baseline->knownKeys($table) : [],
            'baseline_established' => !$baselineAvailable || $this->baseline->isEstablished($table),
            'report_label' => $options['report_label'] ?? $this->reportLabel($table),
        ]);

        $report['source'] = [
            'name' => $options['source_name'] ?? basename($absolutePath),
            'format' => $sample['format'],
            'delimiter' => $sample['delimiter'],
            'header_row' => $sample['header_index'] + 1,
            'metadata' => $sample['metadata'],
            'misaligned_rows' => $sample['misaligned_rows'],
        ];
        $report['analyzed_in_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        $event = null;
        if (($options['record'] ?? true) === true) {
            try {
                $event = $this->registry->record($report, $report['source']['name']);
            } catch (Throwable $e) {
                Log::warning('Schema drift event could not be recorded.', ['table' => $table, 'error' => $e->getMessage()]);
            }
        }

        if (($options['remember'] ?? true) === true) {
            $this->detector->rememberFormats($report);
            if ($baselineAvailable) {
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

        return array_values(array_unique($ignored));
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
