<?php

namespace App\Services\Import\SchemaDrift;

use App\Services\Import\SchemaIntrospectionService;
use App\Services\Import\SmartContentHeaderGuardService;
use App\Support\Import\ColumnValueProfiler;
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
        $sourceHeaders = array_values($sourceHeaders);
        $profiles = $this->profiler->profileColumns($sourceHeaders, $sampleRows);

        $columns = [];
        $newColumns = [];
        $unmappedColumns = [];
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

            $dbColumn = $this->resolveDbColumn($label, $canonical, $meta, $tableColumnsLower);
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

        $pendingNew = array_values(array_filter($newColumns, static fn (array $column): bool => $column['decision'] === 'pending'));
        $severities = array_column($adaptations, 'severity');
        $status = match (true) {
            $pendingNew !== [] => self::STATUS_DRIFT,
            in_array(self::SEVERITY_DANGER, $severities, true), in_array(self::SEVERITY_WARNING, $severities, true) => self::STATUS_ATTENTION,
            $adaptations !== [] => self::STATUS_ADAPTED,
            default => self::STATUS_MATCH,
        };

        $formatChanges = array_values(array_filter($adaptations, static fn (array $item): bool => ($item['code'] ?? '') === 'format_changed'));

        return [
            'table' => $table,
            'report_label' => $options['report_label'] ?? null,
            'status' => $status,
            'headline' => $this->headline($status, count($pendingNew), count($adaptations), $missing, count($unmappedColumns), $baselineEstablished),
            'stats' => [
                'source_columns' => count(array_filter($columns, static fn (array $column): bool => $column['state'] !== 'ignored')),
                'matched' => count($matchedDb),
                'new' => count($pendingNew),
                'unmapped' => count($unmappedColumns),
                'rejected' => count($newColumns) - count($pendingNew),
                'missing' => count($missing),
                'adaptations' => count($adaptations),
                'sample_rows' => count($sampleRows),
            ],
            'columns' => $columns,
            'new_columns' => $newColumns,
            'unmapped_columns' => $unmappedColumns,
            'baseline_established' => $baselineEstablished,
            'missing_columns' => $missing,
            'adaptations' => $adaptations,
            'date_orders' => $dateOrders,
            'fingerprint' => $this->fingerprint($table, $pendingNew, $formatChanges),
            'has_pending_decision' => $pendingNew !== [],
            'has_blocking_issue' => in_array(self::SEVERITY_DANGER, $severities, true),
        ];
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
                    $findings[] = $this->finding(self::SEVERITY_WARNING, 'fraction_rounded', 'Pecahan dibulatkan', "Kolom {$dbColumn} bertipe {$dbType}; nilai desimal sumber akan dibulatkan.");
                }
                if ($isDecimalTarget && (int) ($number['max_scale'] ?? 0) > $scale) {
                    $findings[] = $this->finding(self::SEVERITY_INFO, 'scale_rounded', 'Desimal dibulatkan', sprintf('Sumber memiliki %d angka desimal; disimpan dengan %d desimal sesuai %s.', (int) $number['max_scale'], $scale, $dbType));
                }
                if ($isDecimalTarget && $precision > 0 && (int) ($number['max_integer_digits'] ?? 0) > $precision - $scale) {
                    $findings[] = $this->finding(self::SEVERITY_DANGER, 'numeric_overflow', 'Angka melebihi kapasitas', sprintf('Nilai terbesar memiliki %d digit bulat, melebihi kapasitas %s.', (int) $number['max_integer_digits'], $dbType));
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
                $findings[] = $this->finding(self::SEVERITY_WARNING, 'text_truncated', 'Teks melebihi kapasitas', sprintf('Nilai terpanjang %d karakter melebihi %s; kelebihannya akan terpotong.', (int) $profile['max_length'], $dbType));
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

    private function resolveDbColumn(string $label, string $canonical, array $meta, array $tableColumnsLower): ?string
    {
        if (isset($meta[$canonical])) {
            return $canonical;
        }

        $snake = $this->normalizeName($label);
        if (isset($meta[$snake])) {
            return $snake;
        }

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

    private function headline(string $status, int $newCount, int $adaptationCount, array $missing, int $unmappedCount = 0, bool $baselineEstablished = true): string
    {
        $headline = match ($status) {
            self::STATUS_DRIFT => "{$newCount} kolom baru terdeteksi di dokumen sumber — perlu keputusan Terima atau Tolak.",
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

    private function fingerprint(string $table, array $pendingNew, array $formatChanges): string
    {
        $parts = [
            'table' => $table,
            'new' => array_map(static fn (array $column): string => $column['proposed_column'], $pendingNew),
            'formats' => array_map(static fn (array $change): string => ($change['column'] ?? '') . ':' . ($change['to'] ?? ''), $formatChanges),
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
