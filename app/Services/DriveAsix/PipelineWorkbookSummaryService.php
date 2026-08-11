<?php

namespace App\Services\DriveAsix;

use App\Models\DriveAsixFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use XMLReader;
use ZipArchive;

class PipelineWorkbookSummaryService
{
    private const CACHE_VERSION = 'v7-area6-pipeline-identities';

    private const MAX_HEADER_ROWS = 80;

    private const MAX_COLUMNS = 120;

    private const MAX_ROWS_PER_SHEET = 500_000;

    /** @var array<string, string> */
    private const BRANCHES = [
        'madiun' => 'KC Madiun',
        'magetan' => 'KC Magetan',
        'ngawi' => 'KC Ngawi',
        'ponorogo' => 'KC Ponorogo',
    ];

    /** @var array<string, string> */
    private const BRANCH_CODES = [
        '45' => 'madiun',
        '49' => 'magetan',
        '57' => 'ngawi',
        '70' => 'ponorogo',
    ];

    /**
     * @param  Collection<int, DriveAsixFile>  $files
     * @return array<string, mixed>
     */
    public function summarize(Collection $files): array
    {
        $branchTotals = collect(self::BRANCHES)
            ->map(fn (string $name, string $key): array => $this->emptyBranch($key, $name))
            ->all();
        $fileSummaries = [];
        $warnings = [];
        $scannedFiles = 0;
        $unmapped = 0;
        $outsideScope = 0;

        foreach ($files as $file) {
            if (! $file->isSpreadsheet()) {
                continue;
            }

            $summary = $this->summarizeFile($file);
            $scannedFiles++;

            if (! empty($summary['error'])) {
                $warnings[] = $file->original_name.': '.$summary['error'];
            }

            if (! ($summary['is_pipeline'] ?? false)) {
                continue;
            }

            $unmapped += (int) ($summary['unmapped'] ?? 0);
            $outsideScope += (int) ($summary['outside_scope'] ?? 0);

            foreach ($summary['branches'] as $key => $branch) {
                if (! isset($branchTotals[$key])) {
                    continue;
                }
                $branchTotals[$key]['total'] += (int) $branch['total'];
                $branchTotals[$key]['followed_up'] += (int) $branch['followed_up'];
                $branchTotals[$key]['pending'] += (int) $branch['pending'];
                $branchTotals[$key]['unclassified'] += (int) $branch['unclassified'];
            }

            $fileSummaries[] = $summary;
        }

        foreach ($branchTotals as &$branch) {
            $branch = $this->withProgress($branch);
        }
        unset($branch);

        $total = array_sum(array_column($branchTotals, 'total'));
        $followedUp = array_sum(array_column($branchTotals, 'followed_up'));
        $pending = array_sum(array_column($branchTotals, 'pending'));
        $unclassified = array_sum(array_column($branchTotals, 'unclassified'));
        $classified = $followedUp + $pending;
        $followUpPercentage = $this->progress($total, $followedUp);
        $statusUnavailableFiles = count(array_filter(
            $fileSummaries,
            static fn (array $file): bool => ! ($file['status_supported'] ?? false)
        ));
        usort($fileSummaries, static fn (array $left, array $right): int => [
            $right['total'],
            $right['followed_up'],
        ] <=> [
            $left['total'],
            $left['followed_up'],
        ]);

        return [
            'generated_at' => now()->toIso8601String(),
            'totals' => [
                'total' => $total,
                'followed_up' => $followedUp,
                'pending' => $pending,
                'unclassified' => $unclassified,
                'classified' => $classified,
                'follow_up_percentage' => $followUpPercentage,
                'progress' => $followUpPercentage,
                'active_files' => count($fileSummaries),
                'scanned_files' => $scannedFiles,
                'unmapped' => $unmapped,
                'outside_scope' => $outsideScope,
                'status_unavailable_files' => $statusUnavailableFiles,
            ],
            'branches' => array_values($branchTotals),
            'files' => array_values($fileSummaries),
            'warnings' => array_slice($warnings, 0, 8),
        ];
    }

    /** @return array<string, mixed> */
    public function summarizeFile(DriveAsixFile $file): array
    {
        $path = Storage::disk('local')->path('drive_asix/'.$file->stored_name);
        if (! is_file($path)) {
            return $this->failedFile($file, 'File fisik tidak ditemukan.');
        }

        $cacheKey = 'bank-pipeline:summary:file:'.hash('sha256', implode('|', [
            self::CACHE_VERSION,
            $file->getKey(),
            $file->stored_name,
            $file->updated_at?->format('U.u') ?? '',
            (string) filesize($path),
            (string) filemtime($path),
        ]));

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($file, $path): array {
            try {
                $summary = $file->extension() === 'xlsx'
                    ? $this->summarizeXlsx($file, $path)
                    : $this->summarizeLegacyWorkbook($file, $path);

                $summary['id'] = $file->getKey();
                $summary['name'] = $file->original_name;
                $summary['folder'] = $file->folder?->name;
                $summary['classified'] = $summary['followed_up'] + $summary['pending'];
                $summary['follow_up_percentage'] = $this->progress($summary['total'], $summary['followed_up']);
                $summary['progress'] = $summary['follow_up_percentage'];

                return $summary;
            } catch (\Throwable $exception) {
                report($exception);

                return $this->failedFile($file, 'Struktur workbook belum dapat diringkas.');
            }
        });
    }

    /** @return array<string, mixed> */
    private function summarizeXlsx(DriveAsixFile $file, string $path): array
    {
        $sharedStrings = $this->readSharedStrings($path);
        $sheets = $this->worksheetEntries($path);
        $summary = $this->emptyFileSummary($file);

        foreach ($sheets as $sheet) {
            if ($sheet['hidden']) {
                continue;
            }
            $sheetSummary = $this->summarizeRows(
                $this->xlsxRows($path, $sheet['entry'], $sharedStrings),
                $sheet['name'],
                $file
            );
            $this->mergeSheetSummary($summary, $sheetSummary);
        }

        return $summary;
    }

    /** @return array<string, mixed> */
    private function summarizeLegacyWorkbook(DriveAsixFile $file, string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $summary = $this->emptyFileSummary($file);

        try {
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                if ($sheet->getSheetState() !== 'visible') {
                    continue;
                }

                $highestRow = min(self::MAX_ROWS_PER_SHEET, $sheet->getHighestDataRow());
                $highestColumn = min(
                    self::MAX_COLUMNS,
                    Coordinate::columnIndexFromString($sheet->getHighestDataColumn())
                );
                $rows = (function () use ($sheet, $highestRow, $highestColumn): \Generator {
                    for ($row = 1; $row <= $highestRow; $row++) {
                        $values = [];
                        for ($column = 1; $column <= $highestColumn; $column++) {
                            $value = $sheet->getCell([$column, $row])->getCalculatedValue();
                            if ($this->hasValue($value)) {
                                $values[$column] = $value;
                            }
                        }
                        yield $row => $values;
                    }
                })();

                $sheetSummary = $this->summarizeRows($rows, $sheet->getTitle(), $file);
                $this->mergeSheetSummary($summary, $sheetSummary);
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return $summary;
    }

    /**
     * @param  iterable<int, array<int, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarizeRows(iterable $rows, string $sheetName, DriveAsixFile $file): array
    {
        $buffer = [];
        $schema = null;
        $result = [
            'is_pipeline' => false,
            'status_supported' => false,
            'total' => 0,
            'followed_up' => 0,
            'pending' => 0,
            'unclassified' => 0,
            'status_fields' => [],
            'unmapped' => 0,
            'outside_scope' => 0,
            'truncated' => false,
            'branches' => [],
        ];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber > self::MAX_ROWS_PER_SHEET) {
                $result['truncated'] = true;
                break;
            }

            if ($schema === null && $rowNumber <= self::MAX_HEADER_ROWS) {
                $buffer[$rowNumber] = $row;

                continue;
            }

            if ($schema === null) {
                $schema = $this->detectSchema($buffer, $file);
                if ($schema === null) {
                    return $result;
                }
                $result['is_pipeline'] = true;
                $result['status_supported'] = $schema['follow_columns'] !== [];
                $result['status_fields'] = $schema['follow_fields'];
                foreach ($buffer as $bufferedNumber => $bufferedRow) {
                    if ($bufferedNumber > $schema['header_row']) {
                        $this->consumePipelineRow($result, $schema, $bufferedRow, $sheetName, $file);
                    }
                }
                $buffer = [];
            }

            $this->consumePipelineRow($result, $schema, $row, $sheetName, $file);
        }

        if ($schema === null && $buffer !== []) {
            $schema = $this->detectSchema($buffer, $file);
            if ($schema !== null) {
                $result['is_pipeline'] = true;
                $result['status_supported'] = $schema['follow_columns'] !== [];
                $result['status_fields'] = $schema['follow_fields'];
                foreach ($buffer as $bufferedNumber => $bufferedRow) {
                    if ($bufferedNumber > $schema['header_row']) {
                        $this->consumePipelineRow($result, $schema, $bufferedRow, $sheetName, $file);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function detectSchema(array $rows, DriveAsixFile $file): ?array
    {
        $best = null;
        foreach ($rows as $rowNumber => $row) {
            $roles = $this->headerRoles($row);
            if ($roles['identity_columns'] === []) {
                continue;
            }

            $score = count($roles['identity_columns']) * 5
                + count($roles['branch_columns']) * 4
                + count($roles['follow_columns']) * 3;
            if ($best === null || $score > $best['score']) {
                $best = ['row' => $rowNumber, 'score' => $score, 'roles' => $roles];
            }
        }

        if ($best === null) {
            return null;
        }

        $combinedHeaders = [];
        for ($rowNumber = max(1, $best['row'] - 2); $rowNumber <= $best['row']; $rowNumber++) {
            foreach ($rows[$rowNumber] ?? [] as $column => $value) {
                if (! $this->hasValue($value)) {
                    continue;
                }
                $combinedHeaders[$column] = trim(implode(' ', array_filter([
                    $combinedHeaders[$column] ?? null,
                    (string) $value,
                ])));
            }
        }

        $roles = $this->headerRoles($combinedHeaders);
        $roles['follow_columns'] = array_values(array_unique($roles['follow_columns']));

        $pipelineByName = (bool) $file->getAttribute('pipeline_context')
            || preg_match(
                '/(?:^|[^a-z0-9])(pipeline|prospek|leads?)(?:[^a-z0-9]|$)/i',
                $file->original_name
            ) === 1;
        if (! $pipelineByName && $roles['follow_columns'] === []) {
            return null;
        }

        return [
            'header_row' => $best['row'],
            'follow_fields' => array_values(array_unique(array_map(
                static function (int $column) use ($combinedHeaders, $rows, $best): string {
                    $header = trim((string) ($rows[$best['row']][$column]
                        ?? $combinedHeaders[$column]
                        ?? ''));

                    return $header !== ''
                        ? $header
                        : 'Kolom '.Coordinate::stringFromColumnIndex($column).' (status terdeteksi)';
                },
                $roles['follow_columns']
            ))),
            ...$roles,
        ];
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<string, array<int, int>>
     */
    private function headerRoles(array $headers): array
    {
        $roles = [
            'identity_columns' => [],
            'branch_columns' => [],
            'follow_columns' => [],
        ];

        foreach ($headers as $column => $header) {
            $normalized = $this->normalize($header);
            if ($normalized === '') {
                continue;
            }

            if ($this->isIdentityHeader($normalized)) {
                $roles['identity_columns'][] = (int) $column;
            }
            if ($this->isBranchHeader($normalized)) {
                $roles['branch_columns'][] = (int) $column;
            }
            if ($this->isFollowUpHeader($normalized)) {
                $roles['follow_columns'][] = (int) $column;
            }
        }

        return $roles;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $schema
     * @param  array<int, mixed>  $row
     */
    private function consumePipelineRow(
        array &$result,
        array $schema,
        array $row,
        string $sheetName,
        DriveAsixFile $file
    ): void {
        $identity = $this->firstValue($row, $schema['identity_columns']);
        if (! $this->hasValue($identity) || $this->isRepeatedHeader($identity)) {
            return;
        }

        [$branch, $hasExplicitBranch] = $this->resolveBranch(
            $row,
            $schema['branch_columns'],
            $sheetName,
            $file
        );
        if ($branch === null) {
            if ($hasExplicitBranch) {
                $result['outside_scope']++;
            } else {
                $result['unmapped']++;
            }

            return;
        }

        $followedUp = false;
        foreach ($schema['follow_columns'] as $column) {
            if ($this->isMeaningfulFollowUp($row[$column] ?? null)) {
                $followedUp = true;
                break;
            }
        }

        $result['total']++;
        $result['followed_up'] += $followedUp ? 1 : 0;
        $result['pending'] += ! $followedUp && $schema['follow_columns'] !== [] ? 1 : 0;
        $result['unclassified'] += $schema['follow_columns'] === [] ? 1 : 0;
        $result['branches'][$branch] ??= [
            'total' => 0,
            'followed_up' => 0,
            'pending' => 0,
            'unclassified' => 0,
        ];
        $result['branches'][$branch]['total']++;
        $result['branches'][$branch]['followed_up'] += $followedUp ? 1 : 0;
        $result['branches'][$branch]['pending'] += ! $followedUp && $schema['follow_columns'] !== [] ? 1 : 0;
        $result['branches'][$branch]['unclassified'] += $schema['follow_columns'] === [] ? 1 : 0;
    }

    /** @return array{0: ?string, 1: bool} */
    private function resolveBranch(
        array $row,
        array $branchColumns,
        string $sheetName,
        DriveAsixFile $file
    ): array {
        $hasExplicitBranch = false;
        foreach ($branchColumns as $column) {
            $value = $row[$column] ?? null;
            if (! $this->hasValue($value)) {
                continue;
            }
            $hasExplicitBranch = true;
            $branch = $this->branchFromValue($value);
            if ($branch !== null) {
                return [$branch, true];
            }
        }

        if ($hasExplicitBranch) {
            return [null, true];
        }

        foreach ([$sheetName, $file->folder?->name, $file->original_name] as $fallback) {
            $branch = $this->branchFromValue($fallback);
            if ($branch !== null) {
                return [$branch, false];
            }
        }

        return [null, false];
    }

    private function branchFromValue(mixed $value): ?string
    {
        if (! $this->hasValue($value)) {
            return null;
        }

        $plain = trim((string) $value);
        $numeric = ltrim(preg_replace('/\D+/', '', $plain) ?? '', '0');
        if ($numeric !== '' && isset(self::BRANCH_CODES[$numeric])) {
            return self::BRANCH_CODES[$numeric];
        }

        $normalized = $this->normalize($plain);
        $aliases = [
            'madiun' => ['madiun', 'kc mdn', 'kanca mdn'],
            'magetan' => ['magetan', 'kc mgt', 'kanca mgt'],
            'ngawi' => ['ngawi', 'kc ngwi', 'kanca ngwi'],
            'ponorogo' => ['ponorogo', 'kc pnrg', 'kanca pnrg'],
        ];
        foreach ($aliases as $branch => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($normalized, $needle)) {
                    return $branch;
                }
            }
        }

        return null;
    }

    private function isIdentityHeader(string $header): bool
    {
        return preg_match(
            '/\b(nama (debt|debitur|nasabah|merchant|perusahaan|developer|calon|rek(ening)?|usaha|pengusaha|pemilik|peternak)|supplier|suplier|buyer|debitur|nasabah|merchant|developer|cif|acctno|account|nomor rekening|norek|mpan|store id|id pipeline|idpipeline)\b/',
            $header
        ) === 1;
    }

    private function isBranchHeader(string $header): bool
    {
        return preg_match('/\b(cabang|kanca|branch|mainbr|kode kanca|kode cabang)\b/', $header) === 1;
    }

    private function isFollowUpHeader(string $header): bool
    {
        if (preg_match(
            '/\b(belum|prognosa|komitmen|rencana|target|minimal|ratas|status available|status utility|status transactional|status value chain|value chain status)\b/',
            $header
        ) === 1) {
            return false;
        }

        return preg_match(
            '/(^realisasi\b|\b(tindak lanjut|follow up|followup|follow up ulang|tl kunjungan|cek tl|tgl kunjungan|tanggal kunjungan|remark|hasil kunjungan|update tgl realisasi|bulan real|real bulan ini|plafond real|total realisasi|sudah ots|menunggu putusan|sudah putusan|sudah realisasi|putusan$|tl restruk|tgl restruk|tgl realisasi|tanggal realisasi|progress tl|update progress|status rm|status hp)\b)/',
            $header
        ) === 1;
    }

    private function isMeaningfulFollowUp(mixed $value): bool
    {
        if (! $this->hasValue($value)) {
            return false;
        }
        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        $normalized = $this->normalize($value);
        if ($normalized === '' || in_array($normalized, [
            '0', 'na', 'n a', 'null', 'kosong', 'false', 'no', 'not done', 'not yet',
            'belum', 'belum dilakukan', 'belum ditindaklanjuti', 'belum ditindak lanjuti',
            'tidak', 'tidak ada', 'tidak dilakukan',
        ], true)) {
            return false;
        }

        return preg_match('/^(belum|belum ada|belum tl|belum follow|belum ditindaklanjuti)(\b|$)/', $normalized) !== 1;
    }

    private function isRepeatedHeader(mixed $value): bool
    {
        $normalized = $this->normalize($value);

        return $normalized === '' || preg_match(
            '/^(nama (debt|debitur|nasabah|merchant|perusahaan)|debitur|nasabah|merchant|cif|acctno|nomor rekening|norek)$/',
            $normalized
        ) === 1;
    }

    private function normalize(mixed $value): string
    {
        $value = Str::ascii(mb_strtolower(trim((string) $value)));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    private function firstValue(array $row, array $columns): mixed
    {
        foreach ($columns as $column) {
            if ($this->hasValue($row[$column] ?? null)) {
                return $row[$column];
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function readSharedStrings(string $path): array
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new \RuntimeException('Paket XLSX tidak dapat dibuka.');
        }
        $hasSharedStrings = $archive->locateName('xl/sharedStrings.xml') !== false;
        $archive->close();
        if (! $hasSharedStrings) {
            return [];
        }

        $reader = $this->openArchiveXml($path, 'xl/sharedStrings.xml');
        $strings = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                    continue;
                }
                $outer = $reader->readOuterXml();
                $document = new \DOMDocument;
                if (! $document->loadXML($outer, LIBXML_NONET | LIBXML_COMPACT)) {
                    $strings[] = '';

                    continue;
                }
                $parts = [];
                foreach ($document->getElementsByTagName('t') as $node) {
                    $parts[] = $node->textContent;
                }
                $strings[] = implode('', $parts);
            }
        } finally {
            $reader->close();
        }

        return $strings;
    }

    /** @return array<int, array{name: string, entry: string, hidden: bool}> */
    private function worksheetEntries(string $path): array
    {
        $relationships = [];
        $rels = $this->openArchiveXml($path, 'xl/_rels/workbook.xml.rels');
        try {
            while ($rels->read()) {
                if ($rels->nodeType !== XMLReader::ELEMENT || $rels->localName !== 'Relationship') {
                    continue;
                }
                $id = (string) $rels->getAttribute('Id');
                $target = str_replace('\\', '/', (string) $rels->getAttribute('Target'));
                if ($id === '' || $target === '' || str_contains($target, '..')) {
                    continue;
                }
                $relationships[$id] = str_starts_with($target, '/')
                    ? ltrim($target, '/')
                    : 'xl/'.ltrim($target, '/');
            }
        } finally {
            $rels->close();
        }

        $sheets = [];
        $workbook = $this->openArchiveXml($path, 'xl/workbook.xml');
        try {
            while ($workbook->read()) {
                if ($workbook->nodeType !== XMLReader::ELEMENT || $workbook->localName !== 'sheet') {
                    continue;
                }
                $relationshipId = $workbook->getAttributeNs(
                    'id',
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
                );
                $entry = $relationships[(string) $relationshipId] ?? null;
                if ($entry === null) {
                    continue;
                }
                $sheets[] = [
                    'name' => (string) $workbook->getAttribute('name'),
                    'entry' => $entry,
                    'hidden' => in_array((string) $workbook->getAttribute('state'), ['hidden', 'veryHidden'], true),
                ];
            }
        } finally {
            $workbook->close();
        }

        return $sheets;
    }

    /** @return \Generator<int, array<int, mixed>> */
    private function xlsxRows(string $path, string $entry, array $sharedStrings): \Generator
    {
        $reader = $this->openArchiveXml($path, $entry);
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }
                $rowNumber = max(1, (int) $reader->getAttribute('r'));
                $outer = $reader->readOuterXml();
                $document = new \DOMDocument;
                if (! $document->loadXML($outer, LIBXML_NONET | LIBXML_COMPACT)) {
                    continue;
                }
                $values = [];
                foreach ($document->getElementsByTagName('c') as $cell) {
                    $reference = (string) $cell->getAttribute('r');
                    if (preg_match('/^([A-Z]+)\d+$/i', $reference, $match) !== 1) {
                        continue;
                    }
                    $column = Coordinate::columnIndexFromString(strtoupper($match[1]));
                    if ($column > self::MAX_COLUMNS) {
                        continue;
                    }
                    $value = $this->xlsxCellValue($cell, $sharedStrings);
                    if ($this->hasValue($value)) {
                        $values[$column] = $value;
                    }
                }
                yield $rowNumber => $values;
            }
        } finally {
            $reader->close();
        }
    }

    private function xlsxCellValue(\DOMElement $cell, array $sharedStrings): mixed
    {
        $type = $cell->getAttribute('t');
        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($cell->getElementsByTagName('t') as $text) {
                $parts[] = $text->textContent;
            }

            return implode('', $parts);
        }

        $values = $cell->getElementsByTagName('v');
        if ($values->length < 1) {
            return null;
        }
        $value = $values->item(0)?->textContent;
        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? null;
        }
        if ($type === 'b') {
            return $value === '1' ? 'Ya' : 'Tidak';
        }

        return $value;
    }

    private function openArchiveXml(string $path, string $entry): XMLReader
    {
        $realPath = realpath($path);
        if (! is_string($realPath) || $realPath === '' || str_contains($realPath, '#')) {
            throw new \RuntimeException('Lokasi workbook tidak valid.');
        }
        $reader = new XMLReader;
        $uri = 'zip://'.str_replace('\\', '/', $realPath).'#'.$entry;
        if (! $reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new \RuntimeException('XML workbook tidak dapat dibuka.');
        }

        return $reader;
    }

    /** @param  array<string, mixed>  $target */
    private function mergeSheetSummary(array &$target, array $sheet): void
    {
        if (! $sheet['is_pipeline']) {
            return;
        }
        $target['is_pipeline'] = true;
        $target['status_supported'] = $target['status_supported'] || $sheet['status_supported'];
        $target['status_fields'] = array_values(array_unique(array_merge(
            $target['status_fields'],
            $sheet['status_fields']
        )));
        foreach (['total', 'followed_up', 'pending', 'unclassified', 'unmapped', 'outside_scope'] as $key) {
            $target[$key] += (int) $sheet[$key];
        }
        $target['truncated'] = $target['truncated'] || $sheet['truncated'];
        foreach ($sheet['branches'] as $key => $branch) {
            $target['branches'][$key] ??= [
                'total' => 0,
                'followed_up' => 0,
                'pending' => 0,
                'unclassified' => 0,
            ];
            foreach (['total', 'followed_up', 'pending', 'unclassified'] as $metric) {
                $target['branches'][$key][$metric] += (int) $branch[$metric];
            }
        }
    }

    /** @return array<string, mixed> */
    private function emptyFileSummary(DriveAsixFile $file): array
    {
        return [
            'id' => $file->getKey(),
            'name' => $file->original_name,
            'folder' => $file->folder?->name,
            'is_pipeline' => false,
            'status_supported' => false,
            'total' => 0,
            'followed_up' => 0,
            'pending' => 0,
            'unclassified' => 0,
            'status_fields' => [],
            'unmapped' => 0,
            'outside_scope' => 0,
            'truncated' => false,
            'branches' => [],
            'error' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function failedFile(DriveAsixFile $file, string $message): array
    {
        return [
            ...$this->emptyFileSummary($file),
            'pending' => 0,
            'unclassified' => 0,
            'classified' => 0,
            'follow_up_percentage' => 0.0,
            'progress' => 0.0,
            'error' => $message,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyBranch(string $key, string $name): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'total' => 0,
            'followed_up' => 0,
            'pending' => 0,
            'unclassified' => 0,
            'classified' => 0,
            'follow_up_percentage' => 0.0,
            'progress' => 0.0,
        ];
    }

    /** @param  array<string, mixed>  $branch */
    private function withProgress(array $branch): array
    {
        $branch['classified'] = $branch['followed_up'] + $branch['pending'];
        $branch['follow_up_percentage'] = $this->progress($branch['total'], $branch['followed_up']);
        $branch['progress'] = $branch['follow_up_percentage'];

        return $branch;
    }

    private function progress(int $total, int $followedUp): float
    {
        return $total > 0 ? round(($followedUp / $total) * 100, 1) : 0.0;
    }
}
