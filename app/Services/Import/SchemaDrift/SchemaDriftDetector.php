<?php

namespace App\Services\Import\SchemaDrift;

use App\Services\Import\SchemaIntrospectionService;
use App\Services\Import\SmartContentHeaderGuardService;
use App\Support\Import\ColumnValueProfiler;
use App\Support\Import\ImportHeaderAliases;
use App\Support\StrictDateParser;
use Throwable;

/**
 * Compares an incoming source (headers + sample rows) with the target table:
 * - matched columns are checked for value/type compatibility and for format
 *   changes against the remembered baseline (e.g. a date that gained a clock)
 * - unknown source columns become "new column" proposals with a safe column
 *   name and an inferred SQL type, waiting for an accept/reject decision
 * - table columns absent from the source are reported as missing
 */
class SchemaDriftDetector
{
    public const STATUS_MATCH = 'match';
    public const STATUS_ADAPTED = 'adapted';
    public const STATUS_ATTENTION = 'attention';
    public const STATUS_DRIFT = 'drift';

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_DANGER = 'danger';

    public const TYPE_OPTIONS = [
        'DATE', 'DATETIME', 'DECIMAL(20,2)', 'DECIMAL(20,4)', 'DECIMAL(30,6)',
        'INT', 'BIGINT', 'VARCHAR(50)', 'VARCHAR(100)', 'VARCHAR(255)', 'TEXT',
    ];

    private const MANAGED_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    private const RESERVED_WORDS = [
        'add', 'all', 'alter', 'and', 'as', 'asc', 'between', 'by', 'case', 'check', 'column', 'create',
        'database', 'default', 'delete', 'desc', 'distinct', 'drop', 'else', 'exists', 'from', 'group',
        'having', 'in', 'index', 'insert', 'interval', 'into', 'is', 'join', 'key', 'like', 'limit',
        'not', 'null', 'on', 'or', 'order', 'primary', 'range', 'rank', 'select', 'set', 'table', 'then',
        'to', 'union', 'unique', 'update', 'use', 'values', 'when', 'where', 'with', 'year', 'month', 'day',
    ];

    public function __construct(
        private readonly ColumnValueProfiler $profiler,
        private readonly ColumnFormatMemory $memory,
        private readonly SchemaIntrospectionService $schema,
    ) {
    }

    /**
     * @param array<int, string> $sourceHeaders raw labels from the source file
     * @param array<int, array<int, mixed>> $sampleRows positional data rows
     * @param array{
     *     canonical_headers?: array<int, string>,
     *     ignored_headers?: array<int, string>,
     *     managed_columns?: array<int, string>,
     *     rejected_columns?: array<int, string>,
     *     report_label?: string|null
     * } $options
     */
    public function detect(string $table, array $sourceHeaders, array $sampleRows, array $options = []): array
    {
        $table = strtolower(trim($table));
        $meta = $this->schema->getColumnMetadata($table);
        $tableColumnsLower = array_keys($meta);
        $canonicalHeaders = array_values((array) ($options['canonical_headers'] ?? []));
        $ignored = array_fill_keys(array_map('strtolower', (array) ($options['ignored_headers'] ?? [])), true);
        $managed = array_fill_keys(array_map('strtolower', array_merge(self::MANAGED_COLUMNS, (array) ($options['managed_columns'] ?? []))), true);
        $rejected = array_fill_keys(array_map('strtolower', (array) ($options['rejected_columns'] ?? [])), true);
        $knownHeaders = array_fill_keys(array_map('strtolower', (array) ($options['known_headers'] ?? [])), true);
        // Without a baseline option the detector keeps its strict behaviour:
        // every unknown header is a new column.
        $baselineEstablished = (bool) ($options['baseline_established'] ?? true);
        // Accepted renames: canonical source header => existing table column.
        $aliases = [];
        foreach ((array) ($options['column_aliases'] ?? []) as $aliasHeader => $aliasColumn) {
            $aliasColumn = strtolower(trim((string) $aliasColumn));
            if (isset($meta[$aliasColumn])) {
                $aliases[strtolower(trim((string) $aliasHeader))] = $aliasColumn;
            }
        }
        $previousDocument = (array) ($options['previous_document'] ?? []);
        $sourceHeaders = array_values($sourceHeaders);
        $profiles = $this->profiler->profileColumns($sourceHeaders, $sampleRows);
        $resolution = $this->resolveColumns($sourceHeaders, $canonicalHeaders, $ignored, $aliases, $meta, $tableColumnsLower);

        $columns = [];
        $newColumns = [];
        $unmappedColumns = [];
        $adjustments = [];
        $adaptations = [];
        $matchedDb = [];
        $dateOrders = [];
        $proposedNames = [];

        foreach ($sourceHeaders as $index => $rawHeader) {
            $label = trim((string) $rawHeader);
            $canonical = strtolower(trim((string) ($canonicalHeaders[$index] ?? $this->normalizeName($label))));
            $profile = $profiles[$index];

            if ($label === '' || $canonical === '' || preg_match('/^col_\d+$/', $canonical) === 1 || isset($ignored[$canonical])) {
                $columns[] = $this->columnEntry($index, $label, $canonical, null, 'ignored', $profile, []);
                continue;
            }

            $dbColumn = $resolution[$index] ?? null;
            if ($dbColumn !== null && isset($managed[$dbColumn])) {
                $columns[] = $this->columnEntry($index, $label, $canonical, $dbColumn, 'ignored', $profile, []);
                continue;
            }

            if ($dbColumn !== null && isset($matchedDb[$dbColumn])) {
                $columns[] = $this->columnEntry($index, $label, $canonical, $dbColumn, 'duplicate', $profile, [[
                    'severity' => self::SEVERITY_WARNING,
                    'code' => 'duplicate_source',
                    'title' => 'Kolom ganda',
                    'detail' => "Kolom \"{$label}\" memetakan ke {$dbColumn} yang sudah diisi kolom sumber lain; nilai kolom ini diabaikan.",
                ]]);
                continue;
            }

            if ($dbColumn !== null) {
                $matchedDb[$dbColumn] = true;
                $findings = $this->assess($table, $dbColumn, $meta[$dbColumn], $profile);
                foreach ($findings as $finding) {
                    $adaptations[] = $finding + ['column' => $dbColumn, 'header' => $label];
                    if (isset($finding['adjustment'])) {
                        $adjustment = $finding['adjustment'] + ['header' => $label, 'finding' => $finding['code']];
                        $adjustment['decision'] = isset($rejected['adjust:' . $dbColumn]) ? 'rejected' : 'pending';
                        $adjustments[$dbColumn] = $adjustment;
                    }
                }

                $order = $profile['date']['order'] ?? null;
                if (in_array($order, [StrictDateParser::ORDER_DAY_FIRST, StrictDateParser::ORDER_MONTH_FIRST], true)
                    && ($profile['date']['order_basis'] ?? null) !== ColumnValueProfiler::ORDER_CONFLICT) {
                    $dateOrders[$dbColumn] = $order;
                }

                $columns[] = $this->columnEntry($index, $label, $canonical, $dbColumn, 'matched', $profile, $findings);
                continue;
            }

            $proposedName = $this->proposeColumnName($canonical, $tableColumnsLower, $proposedNames);
            $proposedNames[] = $proposedName;
            $proposedType = $this->proposeSqlType($profile);
            $isRejected = isset($rejected[$proposedName]);

            // A header the report has always carried (or the very first document
            // of a table, which defines the baseline) is an existing column the
            // import never stored — not a sudden addition worth a decision.
            if (!$isRejected && (!$baselineEstablished || isset($knownHeaders[$canonical]))) {
                $unmappedColumns[] = [
                    'index' => $index,
                    'header' => $label,
                    'proposed_column' => $proposedName,
                    'proposed_type' => $proposedType,
                    'kind' => $profile['kind'],
                    'label' => $profile['label'],
                    'samples' => array_slice((array) $profile['samples'], 0, 3),
                ];
                $columns[] = $this->columnEntry($index, $label, $canonical, null, 'unmapped', $profile, []);
                continue;
            }

            $newColumns[] = [
                'index' => $index,
                'header' => $label,
                'canonical' => $canonical,
                'proposed_column' => $proposedName,
                'proposed_type' => $proposedType,
                'type_options' => array_values(array_unique(array_merge([$proposedType], self::TYPE_OPTIONS))),
                'kind' => $profile['kind'],
                'label' => $profile['label'],
                'confidence' => $profile['confidence'],
                'samples' => $profile['samples'],
                'non_empty' => $profile['non_empty'],
                'reason' => $this->typeReason($profile, $proposedType),
                'decision' => $isRejected ? 'rejected' : 'pending',
            ];
            $columns[] = $this->columnEntry($index, $label, $canonical, null, $isRejected ? 'rejected' : 'new', $profile, []);
        }

        $missing = [];
        foreach ($meta as $lowerColumn => $columnMeta) {
            if (isset($matchedDb[$lowerColumn]) || isset($managed[$lowerColumn]) || str_starts_with($lowerColumn, 'uniqueid')) {
                continue;
            }
            $missing[] = (string) ($columnMeta['name'] ?? $lowerColumn);
        }

        // Columns the previous document of this report carried but this one does
        // not: either renamed in the source (map the new header onto them) or
        // dropped (the import would silently leave them empty).
        $dropped = $this->droppedColumns($previousDocument, $matchedDb, $meta, $managed);
        $newColumns = $this->suggestRenames($table, $newColumns, $profiles, $dropped);
        $mappedTargets = array_filter(array_map(
            static fn (array $column): ?string => ($column['suggestion']['action'] ?? null) === 'map' ? $column['suggestion']['column'] : null,
            $newColumns
        ));
        foreach ($dropped as $droppedColumn => $previous) {
            if (in_array($droppedColumn, $mappedTargets, true)) {
                continue;
            }
            $adaptations[] = $this->finding(self::SEVERITY_WARNING, 'column_dropped', 'Kolom hilang dari dokumen sumber', sprintf(
                'Dokumen sebelumnya membawa kolom "%s" (contoh: %s), dokumen ini tidak. Kolom %s akan kosong untuk import ini.',
                (string) ($previous->header_label ?: $droppedColumn),
                (string) ($previous->example ?: '-'),
                $droppedColumn
            )) + ['column' => $droppedColumn, 'header' => (string) ($previous->header_label ?: $droppedColumn)];
        }

        $pendingNew = array_values(array_filter($newColumns, static fn (array $column): bool => $column['decision'] === 'pending'));
        $pendingAdjustments = array_values(array_filter($adjustments, static fn (array $adjustment): bool => $adjustment['decision'] === 'pending'));
        $hasPendingDecision = $pendingNew !== [] || $pendingAdjustments !== [];
        $severities = array_column($adaptations, 'severity');
        $status = match (true) {
            $hasPendingDecision => self::STATUS_DRIFT,
            in_array(self::SEVERITY_DANGER, $severities, true), in_array(self::SEVERITY_WARNING, $severities, true) => self::STATUS_ATTENTION,
            $adaptations !== [] => self::STATUS_ADAPTED,
            default => self::STATUS_MATCH,
        };

        $formatChanges = array_values(array_filter($adaptations, static fn (array $item): bool => ($item['code'] ?? '') === 'format_changed'));

        return [
            'table' => $table,
            'report_label' => $options['report_label'] ?? null,
            'status' => $status,
            'headline' => $this->headline($status, count($pendingNew), count($adaptations), $missing, count($unmappedColumns), $baselineEstablished, count($pendingAdjustments), count($mappedTargets)),
            'stats' => [
                'source_columns' => count(array_filter($columns, static fn (array $column): bool => $column['state'] !== 'ignored')),
                'matched' => count($matchedDb),
                'new' => count($pendingNew),
                'renamed' => count($mappedTargets),
                'unmapped' => count($unmappedColumns),
                'rejected' => count($newColumns) - count($pendingNew),
                'missing' => count($missing),
                'dropped' => count($dropped) - count(array_intersect(array_keys($dropped), $mappedTargets)),
                'adjustments' => count($pendingAdjustments),
                'adaptations' => count($adaptations),
                'sample_rows' => count($sampleRows),
            ],
            'columns' => $columns,
            'new_columns' => $newColumns,
            'adjustments' => array_values($adjustments),
            'unmapped_columns' => $unmappedColumns,
            'baseline_established' => $baselineEstablished,
            'missing_columns' => $missing,
            'adaptations' => $adaptations,
            'date_orders' => $dateOrders,
            'fingerprint' => $this->fingerprint($table, $pendingNew, $formatChanges, $pendingAdjustments),
            'has_pending_decision' => $hasPendingDecision,
            'has_blocking_issue' => in_array(self::SEVERITY_DANGER, $severities, true),
        ];
    }

    /**
     * @return array<string, object> db column => previous-document baseline row
     */
    private function droppedColumns(array $previousDocument, array $matchedDb, array $meta, array $managed): array
    {
        $previousColumns = [];
        foreach ($previousDocument as $row) {
            $column = strtolower(trim((string) ($row->db_column ?? '')));
            if ($column !== '' && isset($meta[$column]) && !isset($managed[$column])) {
                $previousColumns[$column] = $row;
            }
        }

        if ($previousColumns === []) {
            return [];
        }

        $dropped = array_diff_key($previousColumns, $matchedDb);
        $kept = count($previousColumns) - count($dropped);

        // A column that only ever arrived empty loses nothing when it disappears.
        $dropped = array_filter($dropped, static function ($row): bool {
            $signature = strtolower(trim((string) ($row->signature ?? '')));

            return $signature !== '' && $signature !== ColumnValueProfiler::KIND_EMPTY;
        });

        // A different layout variant of the same report (e.g. the Excel and the
        // CSV export) shares few columns with the previous document; comparing
        // them column by column would only produce noise.
        if ($dropped === [] || $kept < count($previousColumns) * 0.6) {
            return [];
        }

        return $dropped;
    }

    /**
     * Pairs new source headers with columns the previous document carried, when
     * the data has the same shape and the names are related (renamed export
     * label). The import then fills the existing column instead of a new one.
     *
     * @param array<string, object> $dropped
     */
    private function suggestRenames(string $table, array $newColumns, array $profiles, array $dropped): array
    {
        if ($dropped === []) {
            return $newColumns;
        }

        $pending = array_keys(array_filter($newColumns, static fn (array $column): bool => $column['decision'] === 'pending'));
        $candidates = [];
        foreach ($pending as $position) {
            $column = $newColumns[$position];
            $profile = $profiles[$column['index']] ?? [];
            foreach ($dropped as $target => $previous) {
                $previousSignature = (string) ($previous->signature ?? $this->memory->previous($table, $target)?->signature ?? '');
                $currentSignature = (string) ($profile['signature'] ?? '');
                if ($previousSignature !== '' && $currentSignature !== ''
                    && self::isMeaningfulFormatChange($previousSignature, $currentSignature)) {
                    continue;
                }

                $nameScore = max(
                    $this->nameSimilarity((string) $column['proposed_column'], $target),
                    $this->nameSimilarity((string) $column['proposed_column'], $this->normalizeName((string) ($previous->header_label ?? '')))
                );
                $loneRename = count($pending) === 1 && count($dropped) === 1 && $previousSignature !== '' && $currentSignature !== '';
                if ($nameScore < 0.5 && !$loneRename) {
                    continue;
                }

                $candidates[] = [$position, $target, $nameScore + ($previousSignature === $currentSignature ? 0.25 : 0), $previous];
            }
        }

        usort($candidates, static fn (array $a, array $b): int => $b[2] <=> $a[2]);
        $usedPositions = [];
        $usedTargets = [];
        foreach ($candidates as [$position, $target, $score, $previous]) {
            if (isset($usedPositions[$position]) || isset($usedTargets[$target])) {
                continue;
            }
            $usedPositions[$position] = true;
            $usedTargets[$target] = true;
            $newColumns[$position]['suggestion'] = [
                'action' => 'map',
                'column' => $target,
                'confidence' => round(min(1, $score), 2),
                'reason' => sprintf(
                    'Kemungkinan kolom "%s" berganti nama: dokumen sebelumnya membawa "%s" dengan format sama (%s), dokumen ini tidak.',
                    $target,
                    (string) ($previous->header_label ?: $target),
                    (string) ($previous->label ?: 'format sama')
                ),
            ];
        }

        return $newColumns;
    }

    private function nameSimilarity(string $left, string $right): float
    {
        if ($left === '' || $right === '') {
            return 0.0;
        }
        if ($left === $right) {
            return 1.0;
        }

        $leftTokens = array_values(array_filter(explode('_', $left)));
        $rightTokens = array_values(array_filter(explode('_', $right)));
        $shared = count(array_intersect($leftTokens, $rightTokens));
        $tokenScore = $shared / max(1, max(count($leftTokens), count($rightTokens)));

        // Abbreviations: "no_rek" vs "nomor_rekening", "tgl_ph" vs "tanggal_ph".
        $prefixHits = 0;
        foreach ($leftTokens as $token) {
            foreach ($rightTokens as $other) {
                if ($token !== $other && strlen($token) >= 2 && strlen($other) >= 2
                    && (str_starts_with($other, $token) || str_starts_with($token, $other))) {
                    $prefixHits++;
                    break;
                }
            }
        }
        $prefixScore = ($shared + $prefixHits) / max(1, max(count($leftTokens), count($rightTokens)));

        similar_text($left, $right, $percent);

        return max($tokenScore, $prefixScore * 0.9, $percent / 100 * 0.8);
    }

    /**
     * Stores the current source formats as the baseline for later comparisons.
     */
    public function rememberFormats(array $report): void
    {
        foreach ((array) ($report['columns'] ?? []) as $column) {
            if (($column['state'] ?? '') !== 'matched' || empty($column['db_column'])) {
                continue;
            }

            $this->memory->remember((string) $report['table'], (string) $column['db_column'], (string) $column['header'], (array) $column['profile_full']);
        }

        $this->memory->flush((string) $report['table']);
    }

    public static function isAllowedType(string $type): bool
    {
        $type = strtoupper(trim($type));

        return in_array($type, self::TYPE_OPTIONS, true)
            || preg_match('/^VARCHAR\((\d{1,4})\)$/', $type, $varchar) === 1 && (int) $varchar[1] >= 1 && (int) $varchar[1] <= 4000
            || preg_match('/^DECIMAL\((\d{1,2}),(\d{1,2})\)$/', $type, $decimal) === 1 && (int) $decimal[1] <= 38 && (int) $decimal[2] <= (int) $decimal[1];
    }

    public static function isSafeColumnName(string $name): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name) === 1
            && !in_array($name, self::RESERVED_WORDS, true);
    }

    private function assess(string $table, string $dbColumn, array $columnMeta, array $profile): array
    {
        $findings = [];
        $kind = (string) $profile['kind'];
        $baseType = strtolower((string) ($columnMeta['base_type'] ?? ''));
        $dbType = strtoupper((string) ($columnMeta['type'] ?? $baseType));
        $nonEmpty = (int) $profile['non_empty'];
        $nonconforming = (int) $profile['nonconforming'];
        $isDateTarget = in_array($baseType, ['date', 'datetime', 'timestamp'], true);
        $isIntegerTarget = in_array($baseType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true);
        $isDecimalTarget = !empty($columnMeta['is_decimal']);

        if ($kind === ColumnValueProfiler::KIND_EMPTY) {
            return [];
        }

        if ($isDateTarget) {
            if (in_array($kind, [ColumnValueProfiler::KIND_DATE, ColumnValueProfiler::KIND_DATETIME], true)) {
                $date = (array) $profile['date'];
                if (!empty($date['has_time']) && $baseType === 'date') {
                    $findings[] = $this->finding(self::SEVERITY_INFO, 'time_ignored', 'Komponen jam diabaikan', sprintf(
                        'Nilai berformat %s (contoh: %s). Kolom %s bertipe DATE, jadi %s; tanggal tetap utuh.',
                        $date['pattern'],
                        $profile['example'] ?? $profile['samples'][0] ?? '-',
                        $dbColumn,
                        !empty($date['meaningful_time']) ? 'jam yang bervariasi tidak disimpan' : 'jam 00:00 diabaikan'
                    ));
                }

                $zeroValues = (int) ($profile['zero_date_values'] ?? 0);
                if ($zeroValues > 0) {
                    $findings[] = StrictDateParser::preservesZeroDates($table)
                        ? $this->finding(self::SEVERITY_INFO, 'zero_date_kept', 'Nilai 0 dipertahankan', sprintf(
                            '%d nilai "0" pada sampel (artinya tidak ada tanggal) disimpan sebagai tanggal nol %s, sama persis dengan dokumen sumber.',
                            $zeroValues,
                            StrictDateParser::ZERO_DATE
                        ))
                        : $this->finding(self::SEVERITY_INFO, 'zero_date_empty', 'Nilai 0 berarti tanpa tanggal', sprintf(
                            '%d nilai "0" pada sampel berarti tidak ada tanggal dan disimpan kosong.',
                            $zeroValues
                        ));
                }

                if (($date['order'] ?? null) === StrictDateParser::ORDER_MONTH_FIRST) {
                    $findings[] = $this->finding(self::SEVERITY_INFO, 'month_first', 'Urutan bulan/hari dikenali', sprintf(
                        'Format %s %s; dikonversi otomatis ke YYYY-MM-DD.',
                        $date['pattern'],
                        ($date['order_basis'] ?? '') === ColumnValueProfiler::ORDER_EVIDENCE
                            ? 'terbukti dari data (ada nilai dengan hari > 12)'
                            : 'mengikuti konvensi core banking (jam 12-jam AM/PM)'
                    ));
                }

                if (($date['order_basis'] ?? null) === ColumnValueProfiler::ORDER_CONFLICT) {
                    $findings[] = $this->finding(self::SEVERITY_WARNING, 'mixed_order', 'Urutan tanggal bercampur', sprintf(
                        'Ditemukan %d nilai hari-dulu dan %d nilai bulan-dulu. Setiap nilai dibaca dari buktinya sendiri; nilai ambigu mengikuti hari-dulu.',
                        (int) ($date['evidence']['day_first_values'] ?? 0),
                        (int) ($date['evidence']['month_first_values'] ?? 0)
                    ));
                }

                if ($nonconforming > 0) {
                    $findings[] = $this->finding(self::SEVERITY_WARNING, 'unparsed_dates', 'Sebagian nilai bukan tanggal', sprintf(
                        '%d dari %d nilai sampel tidak dapat dibaca sebagai tanggal dan akan disimpan kosong.',
                        $nonconforming,
                        $nonEmpty
                    ));
                }
            } else {
                $findings[] = $this->finding(self::SEVERITY_DANGER, 'not_date', 'Isi bukan tanggal', sprintf(
                    'Kolom %s bertipe %s, tetapi isi sumber terbaca sebagai %s (contoh: %s).',
                    $dbColumn,
                    $dbType,
                    strtolower((string) $profile['label']),
                    $profile['example'] ?? $profile['samples'][0] ?? '-'
                ));
            }
        } elseif ($isDecimalTarget || $isIntegerTarget) {
            $numericKinds = [ColumnValueProfiler::KIND_DECIMAL, ColumnValueProfiler::KIND_INTEGER, ColumnValueProfiler::KIND_IDENTIFIER];
            if (!in_array($kind, $numericKinds, true)) {
                $findings[] = $this->finding(self::SEVERITY_DANGER, 'not_numeric', 'Isi bukan angka', sprintf(
                    'Kolom %s bertipe %s, tetapi isi sumber terbaca sebagai %s (contoh: %s).',
                    $dbColumn,
                    $dbType,
                    strtolower((string) $profile['label']),
                    $profile['example'] ?? $profile['samples'][0] ?? '-'
                ));
            } else {
                $number = (array) ($profile['number'] ?? []);
                $scale = (int) ($columnMeta['scale'] ?? 0);
                $precision = (int) ($columnMeta['precision'] ?? 0);
                if ($isIntegerTarget && !empty($number['has_fraction'])) {
                    $findings[] = $this->finding(self::SEVERITY_WARNING, 'fraction_rounded', 'Pecahan dibulatkan', "Kolom {$dbColumn} bertipe {$dbType}; nilai desimal sumber akan dibulatkan.")
                        + $this->adjustmentFor($dbColumn, $dbType, $this->decimalTypeFor(max(18, (int) ($number['max_integer_digits'] ?? 0) + 2), min(4, max(2, (int) ($number['max_scale'] ?? 2)))), 'Ubah ke DECIMAL agar pecahan tersimpan utuh.');
                }
                $integerCapacity = $this->integerDigitCapacity($baseType, (string) ($columnMeta['type'] ?? ''));
                if ($isIntegerTarget && $integerCapacity !== null && (int) ($number['max_digits'] ?? 0) > $integerCapacity) {
                    $findings[] = $this->finding(self::SEVERITY_DANGER, 'integer_overflow', 'Bilangan melebihi kapasitas', sprintf('Nilai terbesar memiliki %d digit, melebihi kapasitas %s (%d digit).', (int) $number['max_digits'], $dbType, $integerCapacity))
                        + $this->adjustmentFor($dbColumn, $dbType, (int) $number['max_digits'] <= 18 ? 'BIGINT' : 'DECIMAL(30,0)', 'Perlebar tipe bilangan agar nilai tidak gagal tersimpan.');
                }
                if ($isDecimalTarget && (int) ($number['max_scale'] ?? 0) > $scale) {
                    $findings[] = $this->finding(self::SEVERITY_INFO, 'scale_rounded', 'Desimal dibulatkan', sprintf('Sumber memiliki %d angka desimal; disimpan dengan %d desimal sesuai %s.', (int) $number['max_scale'], $scale, $dbType));
                }
                if ($isDecimalTarget && $precision > 0 && (int) ($number['max_integer_digits'] ?? 0) > $precision - $scale) {
                    $findings[] = $this->finding(self::SEVERITY_DANGER, 'numeric_overflow', 'Angka melebihi kapasitas', sprintf('Nilai terbesar memiliki %d digit bulat, melebihi kapasitas %s.', (int) $number['max_integer_digits'], $dbType))
                        + $this->adjustmentFor($dbColumn, $dbType, $this->decimalTypeFor((int) $number['max_integer_digits'] + 2, $scale), 'Perbesar presisi DECIMAL; skala desimal dipertahankan.');
                }
                if (($number['style'] ?? '') === 'mixed') {
                    $findings[] = $this->finding(self::SEVERITY_WARNING, 'mixed_separators', 'Pemisah angka bercampur', 'Ditemukan format 1,234.56 dan 1.234,56 sekaligus; setiap nilai dibaca dari pola pemisahnya sendiri.');
                }
                if ($nonconforming > 0) {
                    $findings[] = $this->finding(self::SEVERITY_WARNING, 'unparsed_numbers', 'Sebagian nilai bukan angka', sprintf('%d dari %d nilai sampel bukan angka dan akan disimpan kosong.', $nonconforming, $nonEmpty));
                }
            }
        } elseif (!empty($columnMeta['is_textual'])) {
            $maxLength = (int) ($columnMeta['max_length'] ?? 0);
            if ($maxLength > 0 && (int) $profile['max_length'] > $maxLength) {
                $findings[] = $this->finding(self::SEVERITY_WARNING, 'text_truncated', 'Teks melebihi kapasitas', sprintf('Nilai terpanjang %d karakter melebihi %s; kelebihannya akan terpotong.', (int) $profile['max_length'], $dbType))
                    + (in_array($baseType, ['varchar', 'char'], true)
                        ? $this->adjustmentFor($dbColumn, $dbType, $this->varcharTypeFor((int) $profile['max_length']), 'Perlebar kolom teks agar nilai sumber tersimpan utuh.')
                        : []);
            }
        }

        $previous = $this->memory->previous($table, $dbColumn);
        if ($previous !== null && self::isMeaningfulFormatChange((string) $previous->signature, (string) $profile['signature'])) {
            $compatible = !in_array(self::SEVERITY_DANGER, array_column($findings, 'severity'), true);
            $findings[] = $this->finding(
                $compatible ? self::SEVERITY_INFO : self::SEVERITY_DANGER,
                'format_changed',
                $compatible ? 'Format sumber berubah — tetap kompatibel' : 'Format sumber berubah — tidak kompatibel',
                sprintf(
                    'Sebelumnya %s (contoh: %s), sekarang %s (contoh: %s).%s',
                    (string) ($previous->label ?: $previous->kind),
                    (string) ($previous->example ?? '-'),
                    (string) $profile['label'],
                    $profile['example'] ?? $profile['samples'][0] ?? '-',
                    $compatible ? ' Data tetap masuk dengan nilai yang sama seperti dokumen sumber.' : ''
                )
            ) + [
                'from' => (string) $previous->signature,
                'to' => (string) $profile['signature'],
            ];
        }

        return $findings;
    }

    /**
     * A safe widening proposal attached to a finding. Only ever grows a column:
     * existing values keep their meaning, nothing is narrowed or reinterpreted.
     */
    private function adjustmentFor(string $column, string $fromType, string $toType, string $reason): array
    {
        if (strtoupper($fromType) === strtoupper($toType) || !self::isAllowedType($toType)) {
            return [];
        }

        return ['adjustment' => [
            'column' => $column,
            'from_type' => strtoupper($fromType),
            'to_type' => strtoupper($toType),
            'reason' => $reason,
        ]];
    }

    private function varcharTypeFor(int $maxLength): string
    {
        foreach ([50, 100, 150, 255, 500, 1000, 2000, 4000] as $size) {
            if ($size >= (int) ceil($maxLength * 1.2)) {
                return "VARCHAR({$size})";
            }
        }

        return 'TEXT';
    }

    private function decimalTypeFor(int $integerDigits, int $scale): string
    {
        $scale = max(0, min(10, $scale));
        $precision = max(20, $integerDigits + $scale);

        return $precision <= 38 ? "DECIMAL({$precision},{$scale})" : 'DECIMAL(38,' . max(0, 38 - $integerDigits) . ')';
    }

    /**
     * Digit count above which a value can never fit the integer column (the
     * profile knows digit counts, not exact maxima, so this errs on the side
     * of no false alarm: INT flags 11+ digits, BIGINT 20+).
     */
    private function integerDigitCapacity(string $baseType, string $fullType): ?int
    {
        $unsigned = str_contains(strtolower($fullType), 'unsigned');

        return match ($baseType) {
            'tinyint' => 3,
            'smallint' => 5,
            'mediumint' => $unsigned ? 8 : 7,
            'int', 'integer' => 10,
            'bigint' => $unsigned ? 20 : 19,
            default => null,
        };
    }

    /**
     * Resolves every header before any is judged, mirroring the import:
     * accepted renames, exact names and the import's own aliases claim their
     * columns first. A fuzzy family alias (any "balance" header onto any
     * balance column) only fills a column nobody claimed, so a legitimate
     * second column of the source can never become a "duplicate" of the first.
     *
     * @return array<int, string> source index => table column
     */
    private function resolveColumns(array $sourceHeaders, array $canonicalHeaders, array $ignored, array $aliases, array $meta, array $tableColumnsLower): array
    {
        $resolved = [];
        $unresolved = [];
        foreach ($sourceHeaders as $index => $rawHeader) {
            $label = trim((string) $rawHeader);
            $canonical = strtolower(trim((string) ($canonicalHeaders[$index] ?? $this->normalizeName($label))));
            if ($label === '' || $canonical === '' || preg_match('/^col_\d+$/', $canonical) === 1 || isset($ignored[$canonical])) {
                continue;
            }

            $column = $aliases[$canonical] ?? $this->resolveExactColumn($label, $canonical, $meta);
            if ($column !== null) {
                $resolved[$index] = $column;
            } else {
                $unresolved[$index] = $label;
            }
        }

        $claimed = array_fill_keys(array_values($resolved), true);
        foreach ($unresolved as $index => $label) {
            $column = $this->resolveFamilyAlias($label, $meta, $tableColumnsLower);
            if ($column !== null && !isset($claimed[$column])) {
                $resolved[$index] = $column;
                $claimed[$column] = true;
            }
        }

        return $resolved;
    }

    private function resolveExactColumn(string $label, string $canonical, array $meta): ?string
    {
        if (isset($meta[$canonical])) {
            return $canonical;
        }

        $snake = $this->normalizeName($label);
        if (isset($meta[$snake])) {
            return $snake;
        }

        foreach (ImportHeaderAliases::candidates($label) as $candidate) {
            if (isset($meta[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveFamilyAlias(string $label, array $meta, array $tableColumnsLower): ?string
    {
        try {
            $candidate = app(SmartContentHeaderGuardService::class)->resolveAliasCandidate($label, $tableColumnsLower);
            if (is_string($candidate) && isset($meta[strtolower($candidate)])) {
                return strtolower($candidate);
            }
        } catch (Throwable) {
            // Alias resolution is a best-effort enhancement.
        }

        return null;
    }

    private function columnEntry(int $index, string $label, string $canonical, ?string $dbColumn, string $state, array $profile, array $findings): array
    {
        return [
            'index' => $index,
            'header' => $label,
            'canonical' => $canonical,
            'db_column' => $dbColumn,
            'state' => $state,
            'kind' => $profile['kind'],
            'label' => $profile['label'],
            'signature' => $profile['signature'],
            'example' => $profile['example'] ?? null,
            'samples' => array_slice((array) $profile['samples'], 0, 3),
            'confidence' => $profile['confidence'],
            'findings' => $findings,
            'profile_full' => $profile,
        ];
    }

    private function finding(string $severity, string $code, string $title, string $detail): array
    {
        return compact('severity', 'code', 'title', 'detail');
    }

    private function proposeColumnName(string $canonical, array $existing, array $alreadyProposed): string
    {
        $name = $this->normalizeName($canonical);
        if ($name === '') {
            $name = 'kolom_baru';
        }
        if (preg_match('/^[a-z]/', $name) !== 1) {
            $name = 'col_' . $name;
        }
        if (in_array($name, self::RESERVED_WORDS, true)) {
            $name .= '_col';
        }
        $name = substr($name, 0, 60);

        $candidate = $name;
        $suffix = 2;
        while (in_array($candidate, $existing, true) || in_array($candidate, $alreadyProposed, true)) {
            $candidate = substr($name, 0, 57) . '_' . $suffix++;
        }

        return $candidate;
    }

    private function proposeSqlType(array $profile): string
    {
        $number = (array) ($profile['number'] ?? []);

        return match ($profile['kind']) {
            ColumnValueProfiler::KIND_DATE => 'DATE',
            ColumnValueProfiler::KIND_DATETIME => 'DATETIME',
            ColumnValueProfiler::KIND_DECIMAL => (int) ($number['max_integer_digits'] ?? 0) > 16 || (int) ($number['max_scale'] ?? 0) > 4
                ? 'DECIMAL(30,6)'
                : ((int) ($number['max_scale'] ?? 0) > 2 ? 'DECIMAL(20,4)' : 'DECIMAL(20,2)'),
            ColumnValueProfiler::KIND_INTEGER => (int) ($number['max_digits'] ?? 0) <= 9
                ? 'INT'
                : ((int) ($number['max_digits'] ?? 0) <= 18 ? 'BIGINT' : 'DECIMAL(30,6)'),
            ColumnValueProfiler::KIND_IDENTIFIER => (int) $profile['max_length'] <= 30 ? 'VARCHAR(50)' : ((int) $profile['max_length'] <= 70 ? 'VARCHAR(100)' : 'VARCHAR(255)'),
            ColumnValueProfiler::KIND_TEXT => match (true) {
                (int) $profile['max_length'] <= 20 => 'VARCHAR(50)',
                (int) $profile['max_length'] <= 70 => 'VARCHAR(100)',
                (int) $profile['max_length'] <= 190 => 'VARCHAR(255)',
                default => 'TEXT',
            },
            default => 'VARCHAR(255)',
        };
    }

    private function typeReason(array $profile, string $type): string
    {
        $samples = implode(', ', array_slice((array) $profile['samples'], 0, 2));

        return match ($profile['kind']) {
            ColumnValueProfiler::KIND_EMPTY => 'Kolom belum berisi data pada sampel; VARCHAR(255) dipilih sebagai tipe paling aman.',
            ColumnValueProfiler::KIND_IDENTIFIER => "Berisi kode/nomor ({$samples}); disimpan sebagai teks agar nol di depan dan digit panjang tidak hilang.",
            default => sprintf('Terdeteksi %s dengan keyakinan %d%% (contoh: %s) → %s.', strtolower((string) $profile['label']), (int) round(((float) $profile['confidence']) * 100), $samples ?: '-', $type),
        };
    }

    private function headline(string $status, int $newCount, int $adaptationCount, array $missing, int $unmappedCount = 0, bool $baselineEstablished = true, int $adjustmentCount = 0, int $renameCount = 0): string
    {
        $decisions = array_filter([
            $newCount - $renameCount > 0 ? ($newCount - $renameCount) . ' kolom baru' : null,
            $renameCount > 0 ? $renameCount . ' kolom kemungkinan berganti nama' : null,
            $adjustmentCount > 0 ? $adjustmentCount . ' kolom perlu diperlebar' : null,
        ]);
        $headline = match ($status) {
            self::STATUS_DRIFT => ucfirst(implode(', ', $decisions)) . ' — perlu keputusan Terima atau Tolak.',
            self::STATUS_ATTENTION => 'Struktur cocok, tetapi ada isi kolom yang perlu diperhatikan.',
            self::STATUS_ADAPTED => "Struktur cocok. {$adaptationCount} perbedaan format dikenali dan disesuaikan otomatis.",
            default => $missing === [] && $unmappedCount === 0
                ? 'Struktur dan format dokumen sumber identik dengan tabel tujuan.'
                : 'Struktur cocok dengan tabel tujuan.',
        };

        if (!$baselineEstablished) {
            $headline .= ' Dokumen ini menjadi acuan struktur; kolom yang muncul di import berikutnya akan diberitahukan.';
        } elseif ($unmappedCount > 0 && $status !== self::STATUS_DRIFT) {
            $headline .= " {$unmappedCount} kolom sumber memang tidak disimpan sejak awal.";
        }

        return $headline;
    }

    /**
     * Whether two remembered source formats differ in a way a person should
     * know about. Sample variance (a column that happened to carry no thousand
     * separator, an integer vs. a decimal sample, a code with or without a
     * leading zero) is not a format change; a flipped day/month order, a clock
     * that appeared or disappeared, a swapped decimal separator or a column
     * that stopped being a date is.
     */
    public static function isMeaningfulFormatChange(string $from, string $to): bool
    {
        if ($from === $to || $from === '' || $to === '') {
            return false;
        }

        $fromParts = explode('|', $from);
        $toParts = explode('|', $to);
        $fromKind = $fromParts[0];
        $toKind = $toParts[0];

        if (in_array(ColumnValueProfiler::KIND_EMPTY, [$fromKind, $toKind], true)) {
            return false;
        }

        if ($fromKind === 'date' && $toKind === 'date') {
            $fromFamilies = explode('+', (string) ($fromParts[1] ?? ''));
            $toFamilies = explode('+', (string) ($toParts[1] ?? ''));
            $orders = [(string) ($fromParts[2] ?? '-'), (string) ($toParts[2] ?? '-')];
            $orderChanged = !in_array('-', $orders, true) && $orders[0] !== $orders[1];

            return $orderChanged
                || ($fromParts[3] ?? '') !== ($toParts[3] ?? '')
                || array_intersect($fromFamilies, $toFamilies) === [];
        }

        if ($fromKind === 'date' || $toKind === 'date') {
            return true;
        }

        if ($fromKind === 'number' && $toKind === 'number') {
            $styles = [(string) ($fromParts[1] ?? 'plain'), (string) ($toParts[1] ?? 'plain')];
            sort($styles);

            return $styles === ['id', 'us'];
        }

        $textual = [ColumnValueProfiler::KIND_TEXT, ColumnValueProfiler::KIND_IDENTIFIER];
        $numericLike = ['number', ColumnValueProfiler::KIND_IDENTIFIER];
        if ((in_array($fromKind, $textual, true) && in_array($toKind, $textual, true))
            || (in_array($fromKind, $numericLike, true) && in_array($toKind, $numericLike, true))) {
            return false;
        }

        return true;
    }

    /**
     * A decision is identified by what is being decided (new columns and
     * widenings), so the same new column keeps one notification even when the
     * date format of another column changes between two uploads.
     */
    private function fingerprint(string $table, array $pendingNew, array $formatChanges, array $pendingAdjustments = []): string
    {
        $decisions = array_merge(
            array_map(static fn (array $column): string => 'new:' . $column['proposed_column'], $pendingNew),
            array_map(static fn (array $adjustment): string => 'adjust:' . $adjustment['column'] . ':' . $adjustment['to_type'], $pendingAdjustments)
        );
        $parts = [
            'table' => $table,
            'new' => $decisions,
            'formats' => $decisions === []
                ? array_map(static fn (array $change): string => ($change['column'] ?? '') . ':' . ($change['to'] ?? ''), $formatChanges)
                : [],
        ];
        sort($parts['new']);
        sort($parts['formats']);

        return hash('sha256', json_encode($parts));
    }

    private function normalizeName(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $value)), '_');
    }
}
