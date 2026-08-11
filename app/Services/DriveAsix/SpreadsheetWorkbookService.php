<?php

namespace App\Services\DriveAsix;

use App\Exceptions\DriveAsixVersionConflictException;
use App\Exceptions\DriveAsixWorkbookException;
use App\Models\DriveAsixFile;
use App\Support\SpreadsheetFileFormatDetector;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use XMLReader;
use ZipArchive;

class SpreadsheetWorkbookService
{
    private const DISK = 'local';

    private const BASE_PATH = 'drive_asix';

    private const VERSION_PATH = 'drive_asix_versions';

    private const MAX_SHEETS_IN_PAYLOAD = 25;

    private const MAX_SHEETS_PER_WORKBOOK = 255;

    private const MAX_ROWS_IN_PAYLOAD = 2_000;

    private const MAX_COLUMNS_IN_PAYLOAD = 100;

    private const MAX_TOTAL_CELLS_IN_PAYLOAD = 50_000;

    private const MAX_MERGED_CELLS_IN_PAYLOAD = 20_000;

    private const MAX_MERGED_RANGES_IN_PAYLOAD = 1_000;

    private const MAX_MERGED_RANGES_TO_INSPECT = 5_000;

    private const MAX_DIMENSIONS_TO_INSPECT = 10_000;

    private const MAX_CELLS_IN_OPERATION = 20_000;

    private const MAX_TOTAL_CELLS_PER_SAVE = 50_000;

    private const MAX_OPERATIONS = 5_000;

    private const SHEET_CREATION_OPERATION_COST = 200;

    private const VERSION_RETENTION = 10;

    private const MAX_STREAMING_METADATA_BYTES = 4_194_304;

    private const BLOCKED_FORMULA_PATTERN =
        '/(?:WEBSERVICE\s*\(|RTD\s*\(|DDE\s*\(|HYPERLINK\s*\(|'
        .'(?:https?|ftp|file):\/\/|\\\\\\\\|\[[^\]\r\n]{1,255}\][^!\r\n]{0,255}!|'
        .'\|[^!\r\n]{0,1024}!)/i';

    public function read(DriveAsixFile $file): array
    {
        [$path, $format] = $this->resolveWorkbook($file);
        $spreadsheet = $this->load($path, $format);

        try {
            return $this->serializeWorkbook($file, $spreadsheet, $path, $format);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function validateUploadedWorkbook(
        string $path,
        bool $streamingXlsx = false,
        bool $rejectExternalFormulas = true
    ): string {
        $format = SpreadsheetFileFormatDetector::detect($path);
        if (! in_array($format, DriveAsixFile::SPREADSHEET_EXTENSIONS, true)) {
            throw new DriveAsixWorkbookException('Isi file bukan workbook XLSX/XLS yang valid.');
        }

        if ($streamingXlsx && $format === 'xlsx') {
            $this->validateXlsxStreaming($path, $rejectExternalFormulas);

            return $format;
        }

        $spreadsheet = $this->load($path, $format);
        try {
            if ($spreadsheet->hasMacros()) {
                throw new DriveAsixWorkbookException('Workbook dengan macro/VBA tidak diizinkan di DriveASIX.');
            }
            if ($rejectExternalFormulas && $this->containsUnsafeFormula($spreadsheet)) {
                throw new DriveAsixWorkbookException(
                    'Workbook memuat formula jaringan, hyperlink formula, atau referensi eksternal.'
                );
            }
            if ($spreadsheet->getSheetCount() < 1) {
                throw new DriveAsixWorkbookException('Workbook tidak memiliki sheet.');
            }

            return $format;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Validate an OOXML workbook without materializing worksheet data in PHP.
     *
     * This path is intended for full-fidelity external editors. It preserves
     * the legacy full-load validator as the default for existing callers.
     */
    private function validateXlsxStreaming(string $path, bool $rejectExternalFormulas): void
    {
        $archive = new ZipArchive;
        if ($archive->open($path, ZipArchive::CHECKCONS) !== true) {
            throw new DriveAsixWorkbookException('Paket XLSX rusak atau tidak dapat dibuka.');
        }

        $worksheetEntries = [];
        $seenEntries = [];

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                if (! is_array($entry)) {
                    throw new DriveAsixWorkbookException('Struktur paket XLSX tidak valid.');
                }

                $name = str_replace('\\', '/', (string) ($entry['name'] ?? ''));
                $lowerName = strtolower($name);
                if ($name === ''
                    || str_starts_with($name, '/')
                    || preg_match('/(^|\/)\.\.(\/|$)/', $name) === 1
                    || isset($seenEntries[$lowerName])) {
                    throw new DriveAsixWorkbookException('Struktur path paket XLSX tidak aman.');
                }
                $seenEntries[$lowerName] = true;

                if ($this->isMacroArchiveEntry($lowerName)) {
                    throw new DriveAsixWorkbookException(
                        'Workbook dengan macro/VBA tidak diizinkan di DriveASIX.'
                    );
                }

                if (str_starts_with($lowerName, 'xl/worksheets/')
                    && str_ends_with($lowerName, '.xml')) {
                    $worksheetEntries[] = $name;
                }
            }

            $contentTypesIndex = $archive->locateName('[Content_Types].xml');
            if ($contentTypesIndex === false) {
                throw new DriveAsixWorkbookException('Metadata paket XLSX tidak lengkap.');
            }

            $contentTypesStat = $archive->statIndex($contentTypesIndex);
            $contentTypesSize = is_array($contentTypesStat)
                ? (int) ($contentTypesStat['size'] ?? -1)
                : -1;
            if ($contentTypesSize < 1
                || $contentTypesSize > self::MAX_STREAMING_METADATA_BYTES) {
                throw new DriveAsixWorkbookException('Metadata paket XLSX tidak valid.');
            }

            $contentTypes = $archive->getFromIndex($contentTypesIndex);
            if (! is_string($contentTypes)
                || strlen($contentTypes) !== $contentTypesSize) {
                throw new DriveAsixWorkbookException('Metadata paket XLSX rusak.');
            }
            if ($this->containsMacroMetadata($contentTypes)) {
                throw new DriveAsixWorkbookException(
                    'Workbook dengan macro/VBA tidak diizinkan di DriveASIX.'
                );
            }
        } finally {
            $archive->close();
        }

        try {
            $reader = IOFactory::createReader('Xlsx');
            $worksheetNames = $reader->listWorksheetNames($path);
        } catch (\Throwable $exception) {
            report($exception);
            throw new DriveAsixWorkbookException(
                'Daftar sheet XLSX tidak dapat dibaca.',
                previous: $exception
            );
        }

        if (count($worksheetNames) < 1 || count($worksheetEntries) < 1) {
            throw new DriveAsixWorkbookException('Workbook tidak memiliki sheet.');
        }

        $this->scanWorksheetFormulasStreaming(
            $path,
            $worksheetEntries,
            $rejectExternalFormulas
        );
    }

    private function isMacroArchiveEntry(string $lowerName): bool
    {
        $baseName = basename($lowerName);

        return in_array($baseName, [
            'vbaproject.bin',
            'vbaprojectsignature.bin',
            'vbadata.xml',
        ], true)
            || str_contains($lowerName, '/_vba_project')
            || str_contains($lowerName, '/macros/')
            || str_contains($lowerName, '/macrosheets/');
    }

    private function containsMacroMetadata(string $metadata): bool
    {
        $metadata = strtolower($metadata);

        return str_contains($metadata, 'macroenabled')
            || str_contains($metadata, 'vbaproject')
            || str_contains($metadata, 'vba-project')
            || str_contains($metadata, 'macrosheet');
    }

    /**
     * @param  array<int, string>  $worksheetEntries
     */
    private function scanWorksheetFormulasStreaming(
        string $path,
        array $worksheetEntries,
        bool $rejectExternalFormulas
    ): void {
        $realPath = realpath($path);
        if (! is_string($realPath) || $realPath === '' || str_contains($realPath, '#')) {
            throw new DriveAsixWorkbookException('Lokasi workbook tidak valid untuk validasi streaming.');
        }

        $archiveUri = 'zip://'.str_replace('\\', '/', $realPath).'#';
        $previousInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            foreach ($worksheetEntries as $entry) {
                libxml_clear_errors();
                $xml = new XMLReader;
                $rootSeen = false;

                try {
                    if (! $xml->open(
                        $archiveUri.$entry,
                        null,
                        LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING
                    )) {
                        throw new DriveAsixWorkbookException(
                            'XML worksheet XLSX tidak dapat dibuka.'
                        );
                    }

                    while ($xml->read()) {
                        if ($xml->nodeType === XMLReader::DOC_TYPE) {
                            throw new DriveAsixWorkbookException(
                                'DOCTYPE tidak diizinkan di worksheet XLSX.'
                            );
                        }

                        if (! $rootSeen && $xml->nodeType === XMLReader::ELEMENT) {
                            if ($xml->localName !== 'worksheet') {
                                throw new DriveAsixWorkbookException(
                                    'Root XML worksheet XLSX tidak valid.'
                                );
                            }
                            $rootSeen = true;
                        }

                        if ($xml->nodeType === XMLReader::ELEMENT
                            && $xml->localName === 'f'
                            && $rejectExternalFormulas) {
                            $this->assertUploadedFormulaSafe($xml->readString());
                        }
                    }
                } finally {
                    $xml->close();
                }

                $xmlErrors = libxml_get_errors();
                $hasFatalXmlError = false;
                foreach ($xmlErrors as $xmlError) {
                    if ($xmlError->level >= LIBXML_ERR_ERROR) {
                        $hasFatalXmlError = true;
                        break;
                    }
                }

                if (! $rootSeen || $hasFatalXmlError) {
                    throw new DriveAsixWorkbookException('XML worksheet XLSX rusak.');
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
    }

    private function assertUploadedFormulaSafe(string $formula): void
    {
        if (mb_strlen($formula) > 8_192) {
            throw new DriveAsixWorkbookException('Formula terlalu panjang.');
        }

        if (preg_match(self::BLOCKED_FORMULA_PATTERN, $formula) === 1) {
            throw new DriveAsixWorkbookException(
                'Workbook memuat formula jaringan, hyperlink formula, atau referensi eksternal.'
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $operations
     */
    public function save(
        DriveAsixFile $file,
        string $baseRevision,
        array $operations,
        ?int $userId = null,
        ?string $activeSheet = null
    ): array {
        if (count($operations) > self::MAX_OPERATIONS) {
            throw new DriveAsixWorkbookException(
                'Terlalu banyak perubahan dalam satu penyimpanan. Simpan perubahan secara bertahap.'
            );
        }

        $lock = Cache::lock('drive-asix:workbook:'.$file->getKey(), 600);

        try {
            return $lock->block(8, function () use (
                $file,
                $baseRevision,
                $operations,
                $userId,
                $activeSheet
            ): array {
                [$path, $format] = $this->resolveWorkbook($file);
                $currentRevision = $this->revision($path);

                if (! hash_equals($currentRevision, $baseRevision)) {
                    throw new DriveAsixVersionConflictException($currentRevision);
                }

                $spreadsheet = $this->load($path, $format);
                $spreadsheet->setValueBinder(new AdvancedValueBinder);
                if ($spreadsheet->hasMacros() || $this->containsUnsafeFormula($spreadsheet)) {
                    $spreadsheet->disconnectWorksheets();
                    throw new DriveAsixWorkbookException(
                        'Workbook memuat macro atau formula eksternal yang tidak aman untuk diedit.'
                    );
                }
                $activeSheetIndex = $spreadsheet->getActiveSheetIndex();
                $temporaryPath = dirname($path).DIRECTORY_SEPARATOR
                    .'.'.basename($path).'.edit-'.Str::uuid().'.tmp';

                try {
                    $totalOperationCost = 0;
                    foreach ($operations as $index => $operation) {
                        if (! is_array($operation)) {
                            throw new DriveAsixWorkbookException(
                                'Perubahan ke-'.($index + 1).' tidak valid.'
                            );
                        }
                        $totalOperationCost += $this->estimateOperationCost(
                            $spreadsheet,
                            $operation,
                            $format
                        );
                        if ($totalOperationCost > self::MAX_TOTAL_CELLS_PER_SAVE) {
                            throw new DriveAsixWorkbookException(
                                'Total perubahan dalam satu penyimpanan dibatasi '
                                .number_format(self::MAX_TOTAL_CELLS_PER_SAVE, 0, ',', '.')
                                .' unit kerja agar server tetap responsif.'
                            );
                        }
                        $this->applyOperation($spreadsheet, $operation, $format);
                    }

                    $resolvedActiveSheetIndex = $activeSheet !== null
                        ? $spreadsheet->getIndex($this->sheet($spreadsheet, $activeSheet))
                        : min($activeSheetIndex, max(0, $spreadsheet->getSheetCount() - 1));
                    $spreadsheet->setActiveSheetIndex($resolvedActiveSheetIndex);
                    $this->write($spreadsheet, $temporaryPath, $format);
                    $this->validateWrittenWorkbook($temporaryPath, $format);
                    $latestRevision = $this->revision($path);
                    if (! hash_equals($currentRevision, $latestRevision)) {
                        throw new DriveAsixVersionConflictException($latestRevision);
                    }
                    $versionPath = $this->storeVersion(
                        $file,
                        $path,
                        $format,
                        $currentRevision,
                        $userId
                    );

                    $contents = file_get_contents($temporaryPath);
                    if ($contents === false || $contents === '') {
                        throw new DriveAsixWorkbookException('Hasil penyimpanan workbook kosong.');
                    }

                    (new Filesystem)->replace($path, $contents);
                    clearstatcache(true, $path);

                    try {
                        $file->forceFill([
                            'size_bytes' => filesize($path) ?: 0,
                            'mime_type' => $format === 'xlsx'
                                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                                : 'application/vnd.ms-excel',
                        ])->save();
                    } catch (\Throwable $metadataException) {
                        try {
                            $backup = Storage::disk(self::DISK)->get($versionPath);
                            (new Filesystem)->replace($path, $backup);
                            clearstatcache(true, $path);
                        } catch (\Throwable $restoreException) {
                            report($metadataException);
                            report($restoreException);
                            throw new DriveAsixWorkbookException(
                                'Metadata gagal disimpan dan pemulihan otomatis gagal. Versi cadangan tetap tersedia untuk pemulihan manual.'
                            );
                        }

                        throw $metadataException;
                    }
                } catch (DriveAsixWorkbookException|DriveAsixVersionConflictException $exception) {
                    throw $exception;
                } catch (\Throwable $exception) {
                    report($exception);
                    throw new DriveAsixWorkbookException(
                        'Workbook gagal disimpan. File asli tetap aman; periksa format atau isi perubahan.'
                    );
                } finally {
                    if (is_file($temporaryPath)) {
                        @unlink($temporaryPath);
                    }
                    $spreadsheet->disconnectWorksheets();
                }

                return $this->read($file->fresh());
            });
        } catch (DriveAsixVersionConflictException|DriveAsixWorkbookException $exception) {
            throw $exception;
        } catch (LockTimeoutException) {
            throw new DriveAsixWorkbookException(
                'File sedang disimpan oleh pengguna lain. Tunggu beberapa detik lalu coba lagi.'
            );
        }
    }

    public function detectFormat(DriveAsixFile $file): ?string
    {
        try {
            [$path, $format] = $this->resolveWorkbook($file);

            return is_file($path) ? $format : null;
        } catch (DriveAsixWorkbookException) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveWorkbook(DriveAsixFile $file): array
    {
        if (basename($file->stored_name) !== $file->stored_name) {
            throw new DriveAsixWorkbookException('Lokasi file tidak valid.');
        }

        $relativePath = self::BASE_PATH.'/'.$file->stored_name;
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($relativePath)) {
            throw new DriveAsixWorkbookException('File tidak ditemukan di penyimpanan DriveASIX.');
        }

        $path = $disk->path($relativePath);
        $format = SpreadsheetFileFormatDetector::detect($path);

        if (! in_array($format, DriveAsixFile::SPREADSHEET_EXTENSIONS, true)) {
            throw new DriveAsixWorkbookException(
                'Isi file bukan workbook XLSX/XLS yang valid atau file rusak.'
            );
        }

        return [$path, $format];
    }

    private function load(string $path, string $format): Spreadsheet
    {
        try {
            $reader = IOFactory::createReader($format === 'xlsx' ? 'Xlsx' : 'Xls');
            $reader->setReadDataOnly(false);
            $this->assertSheetCount(count($reader->listWorksheetNames($path)));

            if (method_exists($reader, 'setIncludeCharts')) {
                $reader->setIncludeCharts(true);
            }
            if (method_exists($reader, 'setEnableDrawingPassThrough')) {
                $reader->setEnableDrawingPassThrough(true);
            }

            $spreadsheet = $reader->load($path);
            try {
                $this->assertSheetCount($spreadsheet->getSheetCount());
            } catch (\Throwable $exception) {
                $spreadsheet->disconnectWorksheets();

                throw $exception;
            }

            return $spreadsheet;
        } catch (DriveAsixWorkbookException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            throw new DriveAsixWorkbookException(
                'Workbook tidak dapat dibuka. Pastikan file tidak rusak atau diproteksi password.'
            );
        }
    }

    private function write(Spreadsheet $spreadsheet, string $path, string $format): void
    {
        $writer = IOFactory::createWriter($spreadsheet, $format === 'xlsx' ? 'Xlsx' : 'Xls');

        if (method_exists($writer, 'setIncludeCharts')) {
            $writer->setIncludeCharts(true);
        }
        if (method_exists($writer, 'setPreCalculateFormulas')) {
            $writer->setPreCalculateFormulas(! $this->containsUnsafeFormula($spreadsheet));
        }

        $writer->save($path);
    }

    private function validateWrittenWorkbook(string $path, string $expectedFormat): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw new DriveAsixWorkbookException('Hasil penyimpanan workbook tidak valid.');
        }

        if (SpreadsheetFileFormatDetector::detect($path) !== $expectedFormat) {
            throw new DriveAsixWorkbookException('Format workbook berubah saat penyimpanan.');
        }

        try {
            $reader = IOFactory::createReader($expectedFormat === 'xlsx' ? 'Xlsx' : 'Xls');
            $reader->setReadDataOnly(true);
            $reader->listWorksheetInfo($path);
        } catch (\Throwable $exception) {
            report($exception);
            throw new DriveAsixWorkbookException('Validasi hasil simpan gagal; file asli tidak diubah.');
        }
    }

    private function revision(string $path): string
    {
        $hash = hash_file('sha256', $path);

        if (! is_string($hash) || $hash === '') {
            throw new DriveAsixWorkbookException('Versi file tidak dapat dibaca.');
        }

        return 'sha256:'.$hash;
    }

    private function serializeWorkbook(
        DriveAsixFile $file,
        Spreadsheet $spreadsheet,
        string $path,
        string $format
    ): array {
        $warnings = [];
        $sheetCount = $spreadsheet->getSheetCount();
        $activeSheetIndex = $spreadsheet->getActiveSheetIndex();

        if ($format === 'xls') {
            $warnings[] = 'Format XLS memakai batas lama 65.536 baris dan 256 kolom; komentar tertentu mungkin tidak tersimpan.';
        }
        if ($file->extension() !== $format) {
            $warnings[] = 'Ekstensi nama file tidak sesuai isi. DriveASIX menggunakan format isi '.strtoupper($format).'.';
        }
        if ($sheetCount > self::MAX_SHEETS_IN_PAYLOAD) {
            $warnings[] = 'Hanya '.self::MAX_SHEETS_IN_PAYLOAD
                .' sheet pertama ditampilkan agar editor tetap responsif.';
        }
        if ($this->containsUnsafeFormula($spreadsheet)) {
            $warnings[] = 'Workbook memuat referensi/formula eksternal. Kalkulasi otomatisnya dinonaktifkan saat disimpan.';
        }

        $sheets = [];
        $worksheets = array_slice(
            $spreadsheet->getAllSheets(),
            0,
            self::MAX_SHEETS_IN_PAYLOAD
        );
        $cellDemands = [];
        foreach ($worksheets as $worksheet) {
            $cellDemands[] = $this->payloadCellDemand($worksheet);
        }
        $cellBudgets = $this->allocateCellBudgets($cellDemands);

        foreach ($worksheets as $index => $worksheet) {
            $serializedCellCount = 0;
            $sheets[] = $this->serializeSheet(
                $worksheet,
                $index,
                $warnings,
                $cellBudgets[$index] ?? 0,
                $serializedCellCount
            );
        }

        return [
            'file' => [
                'id' => $file->getKey(),
                'name' => $file->original_name,
                'extension' => $format,
                'revision' => $this->revision($path),
                'size_bytes' => filesize($path) ?: 0,
                'warnings' => array_values(array_unique($warnings)),
                'capabilities' => [
                    'edit' => true,
                    'formulas' => true,
                    'multi_sheet' => true,
                    'formatting' => true,
                    'merge' => true,
                    'freeze' => true,
                    'filter' => true,
                    'sort' => true,
                    'legacy_xls' => $format === 'xls',
                ],
            ],
            'workbook' => [
                'active_sheet' => min(
                    $activeSheetIndex,
                    max(0, count($sheets) - 1)
                ),
                'sheet_count' => $sheetCount,
                'sheets' => $sheets,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function serializeSheet(
        Worksheet $worksheet,
        int $index,
        array &$warnings,
        int $cellBudget,
        int &$serializedCellCount
    ): array {
        $highestRow = max(1, $worksheet->getHighestDataRow());
        $highestColumnIndex = max(
            1,
            Coordinate::columnIndexFromString($worksheet->getHighestDataColumn())
        );
        [$structuralHighestRow, $structuralHighestColumn] = $this->structuralExtents(
            $worksheet,
            $highestRow,
            $highestColumnIndex,
            $warnings
        );
        $maxRow = min(self::MAX_ROWS_IN_PAYLOAD, max(40, $structuralHighestRow));
        $maxColumn = min(self::MAX_COLUMNS_IN_PAYLOAD, max(12, $structuralHighestColumn));

        if ($structuralHighestRow > self::MAX_ROWS_IN_PAYLOAD
            || $structuralHighestColumn > self::MAX_COLUMNS_IN_PAYLOAD) {
            $warnings[] = 'Sheet "'.$worksheet->getTitle().'" terlalu besar; editor menampilkan sampai '
                .self::MAX_ROWS_IN_PAYLOAD.' baris dan '.self::MAX_COLUMNS_IN_PAYLOAD
                .' kolom. Data dan format di luar area itu tetap dipertahankan.';
        }

        $cells = [];
        $serializedCellCount = 0;
        $cellPayloadTruncated = false;
        $lastColumn = Coordinate::stringFromColumnIndex($maxColumn);
        foreach ($worksheet->getRowIterator(1, $maxRow) as $rowIterator) {
            foreach ($rowIterator->getCellIterator('A', $lastColumn, true) as $cell) {
                if ($serializedCellCount >= $cellBudget) {
                    $cellPayloadTruncated = true;

                    break 2;
                }

                $coordinate = $cell->getCoordinate();
                $value = $cell->getValue();
                $formula = $cell->getDataType() === DataType::TYPE_FORMULA
                    ? (string) $value
                    : null;
                if ($formula !== null) {
                    $calculated = $cell->getOldCalculatedValue();
                    $display = $calculated === null
                        ? $formula
                        : NumberFormat::toFormattedString(
                            $calculated,
                            $cell->getStyle()->getNumberFormat()->getFormatCode()
                        );
                } else {
                    $display = $cell->getFormattedValue();
                }

                $cells[$coordinate] = [
                    'value' => $formula === null ? $value : null,
                    'formula' => $formula,
                    'display' => $display,
                    'data_type' => $cell->getDataType(),
                    'style' => $this->serializeStyle($cell->getStyle()),
                ];
                $serializedCellCount++;
            }
        }
        if ($cellPayloadTruncated) {
            $warnings[] = 'Sebagian sel pada sheet "'.$worksheet->getTitle()
                .'" tidak dimuat ke editor karena batas payload workbook. Data asli tetap dipertahankan.';
        }

        $columnWidths = [];
        foreach ($worksheet->getColumnDimensions() as $column => $dimension) {
            if (Coordinate::columnIndexFromString($column) <= $maxColumn && $dimension->getWidth() > 0) {
                $columnWidths[$column] = $dimension->getWidth();
            }
        }

        $rowHeights = [];
        foreach ($worksheet->getRowDimensions() as $row => $dimension) {
            if ($row <= $maxRow && $dimension->getRowHeight() > 0) {
                $rowHeights[(string) $row] = $dimension->getRowHeight();
            }
        }

        $mergedCells = [];
        $mergedCellCount = 0;
        $inspectedMergeRanges = 0;
        foreach ($worksheet->getMergeCells() as $mergedRange) {
            if ($inspectedMergeRanges >= self::MAX_MERGED_RANGES_TO_INSPECT) {
                $warnings[] = 'Sebagian merged cell pada sheet "'.$worksheet->getTitle()
                    .'" tidak dimuat karena jumlahnya melewati batas aman editor.';

                break;
            }
            $inspectedMergeRanges++;

            try {
                [$start, $end] = Coordinate::rangeBoundaries($mergedRange);
                $size = ($end[0] - $start[0] + 1) * ($end[1] - $start[1] + 1);
                if ($start[0] < 1
                    || $start[1] < 1
                    || $end[0] > $maxColumn
                    || $end[1] > $maxRow
                    || count($mergedCells) >= self::MAX_MERGED_RANGES_IN_PAYLOAD
                    || $mergedCellCount + $size > self::MAX_MERGED_CELLS_IN_PAYLOAD) {
                    $warnings[] = 'Merged cell yang berada di luar area aman editor tidak ditampilkan.';

                    continue;
                }
                $mergedCells[] = $mergedRange;
                $mergedCellCount += $size;
            } catch (\Throwable) {
                $warnings[] = 'Merged cell dengan rentang tidak valid diabaikan oleh editor.';
            }
        }

        return [
            'index' => $index,
            'title' => $worksheet->getTitle(),
            'max_row' => $maxRow,
            'max_col' => $maxColumn,
            'data_max_row' => $highestRow,
            'data_max_col' => $highestColumnIndex,
            'freeze_pane' => $worksheet->getFreezePane(),
            'auto_filter' => $worksheet->getAutoFilter()->getRange() ?: null,
            'merged_cells' => array_values($mergedCells),
            'column_widths' => $columnWidths,
            'row_heights' => $rowHeights,
            'cells' => $cells,
        ];
    }

    private function payloadCellDemand(Worksheet $worksheet): int
    {
        $highestDataRow = max(1, $worksheet->getHighestDataRow());
        $highestDataColumn = max(
            1,
            Coordinate::columnIndexFromString($worksheet->getHighestDataColumn())
        );
        $ignoredWarnings = [];
        [$highestRow, $highestColumn] = $this->structuralExtents(
            $worksheet,
            $highestDataRow,
            $highestDataColumn,
            $ignoredWarnings
        );
        $maxRow = min(self::MAX_ROWS_IN_PAYLOAD, max(40, $highestRow));
        $maxColumn = min(self::MAX_COLUMNS_IN_PAYLOAD, max(12, $highestColumn));
        $lastColumn = Coordinate::stringFromColumnIndex($maxColumn);
        $demand = 0;

        foreach ($worksheet->getRowIterator(1, $maxRow) as $rowIterator) {
            foreach ($rowIterator->getCellIterator('A', $lastColumn, true) as $_cell) {
                $demand++;
            }
        }

        return $demand;
    }

    /**
     * @param  array<int, int>  $demands
     * @return array<int, int>
     */
    private function allocateCellBudgets(array $demands): array
    {
        $totalDemand = array_sum($demands);
        if ($totalDemand <= self::MAX_TOTAL_CELLS_IN_PAYLOAD) {
            return $demands;
        }

        $budgets = [];
        $allocated = 0;
        foreach ($demands as $index => $demand) {
            $budget = $demand > 0
                ? max(
                    1,
                    (int) floor(
                        self::MAX_TOTAL_CELLS_IN_PAYLOAD * ($demand / $totalDemand)
                    )
                )
                : 0;
            $budgets[$index] = min($demand, $budget);
            $allocated += $budgets[$index];
        }

        $remaining = self::MAX_TOTAL_CELLS_IN_PAYLOAD - $allocated;
        while ($remaining > 0) {
            $madeProgress = false;
            foreach ($demands as $index => $demand) {
                if (($budgets[$index] ?? 0) >= $demand) {
                    continue;
                }
                $budgets[$index]++;
                $remaining--;
                $madeProgress = true;

                if ($remaining === 0) {
                    break;
                }
            }
            if (! $madeProgress) {
                break;
            }
        }

        return $budgets;
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array{0: int, 1: int}
     */
    private function structuralExtents(
        Worksheet $worksheet,
        int $highestDataRow,
        int $highestDataColumn,
        array &$warnings
    ): array {
        $highestRow = max($highestDataRow, $worksheet->getHighestRow());
        $highestColumn = max(
            $highestDataColumn,
            Coordinate::columnIndexFromString($worksheet->getHighestColumn())
        );
        $inspectedDimensions = 0;
        foreach ($worksheet->getRowDimensions() as $row => $_dimension) {
            if ($inspectedDimensions >= self::MAX_DIMENSIONS_TO_INSPECT) {
                $warnings[] = 'Sebagian dimensi baris/kolom pada sheet "'.$worksheet->getTitle()
                    .'" tidak dianalisis karena jumlahnya melewati batas aman editor.';

                break;
            }
            $inspectedDimensions++;
            $highestRow = max($highestRow, (int) $row);
        }
        foreach ($worksheet->getColumnDimensions() as $column => $_dimension) {
            if ($inspectedDimensions >= self::MAX_DIMENSIONS_TO_INSPECT) {
                $warnings[] = 'Sebagian dimensi baris/kolom pada sheet "'.$worksheet->getTitle()
                    .'" tidak dianalisis karena jumlahnya melewati batas aman editor.';

                break;
            }
            $inspectedDimensions++;

            try {
                $highestColumn = max(
                    $highestColumn,
                    Coordinate::columnIndexFromString((string) $column)
                );
            } catch (\Throwable) {
                $warnings[] = 'Dimensi kolom tidak valid diabaikan oleh editor.';
            }
        }
        $inspectedMergeRanges = 0;

        foreach ($worksheet->getMergeCells() as $mergedRange) {
            if ($inspectedMergeRanges >= self::MAX_MERGED_RANGES_TO_INSPECT) {
                $warnings[] = 'Sebagian merged cell pada sheet "'.$worksheet->getTitle()
                    .'" tidak dianalisis karena jumlahnya melewati batas aman editor.';

                break;
            }
            $inspectedMergeRanges++;

            try {
                [$start, $end] = Coordinate::rangeBoundaries($mergedRange);
                $highestColumn = max($highestColumn, $end[0]);
                $highestRow = max($highestRow, $end[1]);
            } catch (\Throwable) {
                $warnings[] = 'Merged cell dengan rentang tidak valid diabaikan oleh editor.';
            }
        }

        return [$highestRow, $highestColumn];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStyle(Style $style): array
    {
        $font = $style->getFont();
        $alignment = $style->getAlignment();
        $fill = $style->getFill();
        $bottomBorder = $style->getBorders()->getBottom();

        return [
            'bold' => $font->getBold(),
            'italic' => $font->getItalic(),
            'underline' => $font->getUnderline() !== 'none',
            'font_color' => $this->color($font->getColor()->getARGB()),
            'fill_color' => $fill->getFillType() === Fill::FILL_NONE
                ? null
                : $this->color($fill->getStartColor()->getARGB()),
            'font_size' => $font->getSize(),
            'horizontal' => $alignment->getHorizontal(),
            'vertical' => $alignment->getVertical(),
            'wrap' => $alignment->getWrapText(),
            'number_format' => $style->getNumberFormat()->getFormatCode(),
            'border_style' => $bottomBorder->getBorderStyle(),
            'border_color' => $this->color($bottomBorder->getColor()->getARGB()),
        ];
    }

    private function color(?string $argb): ?string
    {
        if ($argb === null || $argb === '') {
            return null;
        }

        $rgb = strlen($argb) === 8 ? substr($argb, 2) : $argb;

        return preg_match('/^[0-9A-F]{6}$/i', $rgb) === 1 ? '#'.strtoupper($rgb) : null;
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function applyOperation(Spreadsheet $spreadsheet, array $operation, string $format): void
    {
        $type = strtolower(trim((string) ($operation['type'] ?? '')));

        if ($type === '') {
            throw new DriveAsixWorkbookException('Jenis perubahan tidak tersedia.');
        }

        if (in_array($type, ['add_sheet', 'rename_sheet', 'delete_sheet', 'duplicate_sheet'], true)) {
            $this->applySheetOperation($spreadsheet, $operation, $type, $format);

            return;
        }

        $worksheet = $this->sheet($spreadsheet, $operation['sheet'] ?? null);

        match ($type) {
            'set_cell' => $this->setCell($worksheet, $operation, $format),
            'clear_range' => $this->clearRange($worksheet, $operation, $format),
            'set_style' => $this->setStyle($worksheet, $operation, $format),
            'merge' => $this->changeMerge($worksheet, $operation, $format, true),
            'unmerge' => $this->changeMerge($worksheet, $operation, $format, false),
            'insert_rows' => $this->insertRows($worksheet, $operation, $format),
            'delete_rows' => $this->deleteRows($worksheet, $operation, $format),
            'insert_columns' => $this->insertColumns($worksheet, $operation, $format),
            'delete_columns' => $this->deleteColumns($worksheet, $operation, $format),
            'set_column_width' => $worksheet->getColumnDimension(
                $this->column($operation['column'] ?? null, $format)
            )->setWidth($this->number($operation['width'] ?? null, 2, 100, 'lebar kolom')),
            'set_row_height' => $worksheet->getRowDimension(
                $this->row($operation['row'] ?? null, $format)
            )->setRowHeight($this->number($operation['height'] ?? null, 8, 250, 'tinggi baris')),
            'freeze_pane' => $this->freezePane($worksheet, $operation, $format),
            'set_auto_filter' => $this->setAutoFilter($worksheet, $operation, $format),
            'sort_range' => $this->sortRange($worksheet, $operation, $format),
            default => throw new DriveAsixWorkbookException('Operasi "'.$type.'" belum didukung.'),
        };
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function estimateOperationCost(
        Spreadsheet $spreadsheet,
        array $operation,
        string $format
    ): int {
        $type = strtolower(trim((string) ($operation['type'] ?? '')));
        if ($type === 'duplicate_sheet') {
            $ignoredWarnings = [];
            [$highestRow, $highestColumn] = $this->structuralExtents(
                $this->sheet($spreadsheet, $operation['sheet'] ?? null),
                1,
                1,
                $ignoredWarnings
            );

            return max(
                self::SHEET_CREATION_OPERATION_COST,
                $this->boundedCellCost($highestRow, $highestColumn)
            );
        }
        if ($type === 'add_sheet') {
            return self::SHEET_CREATION_OPERATION_COST;
        }
        if (in_array($type, ['rename_sheet', 'delete_sheet'], true)) {
            return 1;
        }

        $worksheet = $this->sheet($spreadsheet, $operation['sheet'] ?? null);
        if (in_array($type, ['clear_range', 'set_style', 'merge', 'unmerge', 'sort_range'], true)) {
            return $this->rangeSize($this->range($operation['range'] ?? null, $format));
        }

        if (in_array($type, ['insert_rows', 'delete_rows'], true)) {
            $row = $this->row($operation['row'] ?? null, $format);
            $count = $this->boundedCount($operation['count'] ?? 1);
            $affectedRows = max(1, $worksheet->getHighestDataRow() - $row + 1) + $count;
            $columns = max(
                1,
                Coordinate::columnIndexFromString($worksheet->getHighestDataColumn())
            );

            return $affectedRows * $columns;
        }

        if (in_array($type, ['insert_columns', 'delete_columns'], true)) {
            $column = Coordinate::columnIndexFromString(
                $this->column($operation['column'] ?? null, $format)
            );
            $count = $this->boundedCount($operation['count'] ?? 1);
            $highestColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
            $affectedColumns = max(1, $highestColumn - $column + 1) + $count;

            return $affectedColumns * max(1, $worksheet->getHighestDataRow());
        }

        return 1;
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function changeMerge(
        Worksheet $worksheet,
        array $operation,
        string $format,
        bool $merge
    ): void {
        $range = $this->range($operation['range'] ?? null, $format);
        $this->assertRangeSize($range);

        if ($merge) {
            $worksheet->mergeCells($range);
        } else {
            $worksheet->unmergeCells($range);
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function setCell(Worksheet $worksheet, array $operation, string $format): void
    {
        $address = $this->cellAddress(
            $operation['address'] ?? $operation['cell'] ?? null,
            $format
        );
        $formula = $operation['formula'] ?? null;
        $value = $operation['value'] ?? null;

        if (is_string($formula) && trim($formula) !== '') {
            $formula = trim($formula);
            if (! str_starts_with($formula, '=')) {
                $formula = '='.$formula;
            }
            $this->assertFormulaSafe($formula);
            $worksheet->getCell($address)->setValueExplicit($formula, DataType::TYPE_FORMULA);

            return;
        }

        if (is_string($value) && str_starts_with(ltrim($value), '=')) {
            $formula = ltrim($value);
            $this->assertFormulaSafe($formula);
            $worksheet->getCell($address)->setValueExplicit($formula, DataType::TYPE_FORMULA);

            return;
        }

        if ($value === null) {
            $worksheet->setCellValue($address, null);

            return;
        }

        if (is_array($value) || is_object($value)) {
            throw new DriveAsixWorkbookException('Nilai sel harus berupa teks, angka, tanggal, atau boolean.');
        }

        $worksheet->setCellValue($address, $value);
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function clearRange(Worksheet $worksheet, array $operation, string $format): void
    {
        $range = $this->range($operation['range'] ?? null, $format);
        $this->assertRangeSize($range);

        foreach (Coordinate::extractAllCellReferencesInRange($range) as $address) {
            $worksheet->setCellValue($address, null);
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function setStyle(Worksheet $worksheet, array $operation, string $format): void
    {
        $range = $this->range($operation['range'] ?? null, $format);
        $this->assertRangeSize($range);
        $input = $operation['style'] ?? null;

        if (! is_array($input)) {
            throw new DriveAsixWorkbookException('Format sel tidak valid.');
        }

        $style = [];
        if (array_key_exists('bold', $input)) {
            $style['font']['bold'] = (bool) $input['bold'];
        }
        if (array_key_exists('italic', $input)) {
            $style['font']['italic'] = (bool) $input['italic'];
        }
        if (array_key_exists('underline', $input)) {
            $style['font']['underline'] = (bool) $input['underline'] ? 'single' : 'none';
        }
        if (array_key_exists('font_size', $input)) {
            $style['font']['size'] = $this->number($input['font_size'], 6, 72, 'ukuran font');
        }
        if (array_key_exists('font_color', $input)) {
            $style['font']['color']['argb'] = $this->argb($input['font_color']);
        }
        if (array_key_exists('fill_color', $input)) {
            if ($input['fill_color'] === null || $input['fill_color'] === '') {
                $style['fill']['fillType'] = Fill::FILL_NONE;
            } else {
                $style['fill'] = [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => $this->argb($input['fill_color'])],
                ];
            }
        }
        if (array_key_exists('horizontal', $input)) {
            $horizontal = (string) $input['horizontal'];
            $allowed = [
                Alignment::HORIZONTAL_GENERAL,
                Alignment::HORIZONTAL_LEFT,
                Alignment::HORIZONTAL_CENTER,
                Alignment::HORIZONTAL_RIGHT,
                Alignment::HORIZONTAL_JUSTIFY,
            ];
            if (! in_array($horizontal, $allowed, true)) {
                throw new DriveAsixWorkbookException('Perataan horizontal tidak valid.');
            }
            $style['alignment']['horizontal'] = $horizontal;
        }
        if (array_key_exists('vertical', $input)) {
            $vertical = (string) $input['vertical'];
            $allowed = [
                Alignment::VERTICAL_BOTTOM,
                Alignment::VERTICAL_CENTER,
                Alignment::VERTICAL_TOP,
                Alignment::VERTICAL_JUSTIFY,
            ];
            if (! in_array($vertical, $allowed, true)) {
                throw new DriveAsixWorkbookException('Perataan vertikal tidak valid.');
            }
            $style['alignment']['vertical'] = $vertical;
        }
        if (array_key_exists('wrap', $input)) {
            $style['alignment']['wrapText'] = (bool) $input['wrap'];
        }
        if (array_key_exists('number_format', $input)) {
            $numberFormat = trim((string) $input['number_format']);
            if ($numberFormat === '' || mb_strlen($numberFormat) > 255) {
                throw new DriveAsixWorkbookException('Format angka tidak valid.');
            }
            $style['numberFormat']['formatCode'] = $numberFormat;
        }
        if (array_key_exists('border_style', $input)) {
            $borderStyle = (string) $input['border_style'];
            $allowed = [
                Border::BORDER_NONE,
                Border::BORDER_HAIR,
                Border::BORDER_DOTTED,
                Border::BORDER_DASHED,
                Border::BORDER_THIN,
                Border::BORDER_MEDIUM,
                Border::BORDER_THICK,
                Border::BORDER_DOUBLE,
            ];
            if (! in_array($borderStyle, $allowed, true)) {
                throw new DriveAsixWorkbookException('Gaya garis batas tidak valid.');
            }
            $style['borders']['allBorders'] = [
                'borderStyle' => $borderStyle,
                'color' => [
                    'argb' => $this->argb($input['border_color'] ?? '#64748B'),
                ],
            ];
        }

        if ($style === []) {
            throw new DriveAsixWorkbookException('Tidak ada format yang diubah.');
        }

        $worksheet->getStyle($range)->applyFromArray($style);
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function insertRows(Worksheet $worksheet, array $operation, string $format): void
    {
        $row = $this->row($operation['row'] ?? null, $format);
        $count = $this->boundedCount($operation['count'] ?? 1);
        $maxRow = $format === 'xls' ? 65_536 : 1_048_576;

        if ($worksheet->getHighestDataRow() + $count > $maxRow) {
            throw new DriveAsixWorkbookException(
                'Penyisipan baris melampaui batas format '.strtoupper($format).'.'
            );
        }

        $worksheet->insertNewRowBefore($row, $count);
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function insertColumns(Worksheet $worksheet, array $operation, string $format): void
    {
        $column = $this->column($operation['column'] ?? null, $format);
        $count = $this->boundedCount($operation['count'] ?? 1);
        $maxColumn = $format === 'xls' ? 256 : 16_384;
        $highestColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());

        if ($highestColumn + $count > $maxColumn) {
            throw new DriveAsixWorkbookException(
                'Penyisipan kolom melampaui batas format '.strtoupper($format).'.'
            );
        }

        $worksheet->insertNewColumnBefore($column, $count);
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function deleteRows(Worksheet $worksheet, array $operation, string $format): void
    {
        $row = $this->row($operation['row'] ?? null, $format);
        $count = $this->boundedCount($operation['count'] ?? 1);
        $maxRow = $format === 'xls' ? 65_536 : 1_048_576;

        if ($row + $count - 1 > $maxRow) {
            throw new DriveAsixWorkbookException(
                'Penghapusan baris melampaui batas format '.strtoupper($format).'.'
            );
        }

        $worksheet->removeRow($row, $count);
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function deleteColumns(Worksheet $worksheet, array $operation, string $format): void
    {
        $column = $this->column($operation['column'] ?? null, $format);
        $count = $this->boundedCount($operation['count'] ?? 1);
        $columnIndex = Coordinate::columnIndexFromString($column);
        $maxColumn = $format === 'xls' ? 256 : 16_384;

        if ($columnIndex + $count - 1 > $maxColumn) {
            throw new DriveAsixWorkbookException(
                'Penghapusan kolom melampaui batas format '.strtoupper($format).'.'
            );
        }

        $worksheet->removeColumn($column, $count);
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function freezePane(Worksheet $worksheet, array $operation, string $format): void
    {
        $pane = $operation['pane'] ?? null;

        if ($pane === null || $pane === '') {
            $worksheet->unfreezePane();

            return;
        }

        $worksheet->freezePane($this->cellAddress($pane, $format));
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function setAutoFilter(Worksheet $worksheet, array $operation, string $format): void
    {
        $range = $operation['range'] ?? null;

        if ($range === null || $range === '') {
            $worksheet->setAutoFilter('');

            return;
        }

        $worksheet->setAutoFilter($this->range($range, $format));
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function sortRange(Worksheet $worksheet, array $operation, string $format): void
    {
        $range = $this->range($operation['range'] ?? null, $format);
        $this->assertRangeSize($range);
        $this->assertRangeCanBeSorted($worksheet, $range);
        [$start, $end] = Coordinate::rangeBoundaries($range);
        $sortColumn = $operation['column'] ?? $start[0];
        $sortColumnIndex = is_numeric($sortColumn)
            ? (int) $sortColumn
            : Coordinate::columnIndexFromString($this->column($sortColumn, $format));

        if ($sortColumnIndex < $start[0] || $sortColumnIndex > $end[0]) {
            throw new DriveAsixWorkbookException('Kolom pengurutan berada di luar area terpilih.');
        }

        $direction = strtolower((string) ($operation['direction'] ?? 'asc'));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new DriveAsixWorkbookException('Arah pengurutan tidak valid.');
        }

        $rows = [];
        for ($row = $start[1]; $row <= $end[1]; $row++) {
            $packet = [];
            for ($column = $start[0]; $column <= $end[0]; $column++) {
                $cell = $worksheet->getCell([$column, $row]);
                $packet[$column] = [
                    'value' => $cell->getValue(),
                    'type' => $cell->getDataType(),
                    'style' => $cell->getStyle()->exportArray(),
                ];
            }
            $keyCell = $worksheet->getCell([$sortColumnIndex, $row]);
            $rows[] = [
                'key' => $keyCell->getDataType() === DataType::TYPE_FORMULA
                    ? $keyCell->getOldCalculatedValue()
                    : $keyCell->getValue(),
                'cells' => $packet,
            ];
        }

        usort($rows, static function (array $left, array $right) use ($direction): int {
            $leftValue = $left['key'];
            $rightValue = $right['key'];
            if (is_numeric($leftValue) && is_numeric($rightValue)) {
                $result = (float) $leftValue <=> (float) $rightValue;
            } else {
                $result = strnatcasecmp((string) $leftValue, (string) $rightValue);
            }

            return $direction === 'desc' ? -$result : $result;
        });

        foreach ($rows as $rowOffset => $packet) {
            $targetRow = $start[1] + $rowOffset;
            foreach ($packet['cells'] as $column => $cellPacket) {
                $cell = $worksheet->getCell([(int) $column, $targetRow]);
                $cell->setValueExplicit($cellPacket['value'], $cellPacket['type']);
                $cell->getStyle()->applyFromArray($cellPacket['style']);
            }
        }
    }

    private function assertRangeCanBeSorted(Worksheet $worksheet, string $range): void
    {
        [$start, $end] = Coordinate::rangeBoundaries($range);
        foreach ($worksheet->getMergeCells() as $mergedRange) {
            [$mergedStart, $mergedEnd] = Coordinate::rangeBoundaries($mergedRange);
            if ($start[0] <= $mergedEnd[0]
                && $end[0] >= $mergedStart[0]
                && $start[1] <= $mergedEnd[1]
                && $end[1] >= $mergedStart[1]) {
                throw new DriveAsixWorkbookException(
                    'Pisahkan merged cell sebelum mengurutkan rentang.'
                );
            }
        }

        if ($worksheet->getComments() !== []
            || $worksheet->getHyperlinkCollection() !== []
            || $worksheet->getDataValidationCollection() !== []
            || $worksheet->getConditionalStylesCollection() !== []
            || $worksheet->getDrawingCollection()->count() > 0
            || $worksheet->getChartCollection()->count() > 0) {
            throw new DriveAsixWorkbookException(
                'Sort lokal hanya tersedia pada sheet tanpa komentar, hyperlink, validation, gambar, chart, atau conditional formatting.'
            );
        }

        for ($row = $start[1]; $row <= $end[1]; $row++) {
            for ($column = $start[0]; $column <= $end[0]; $column++) {
                if ($worksheet->getCell([$column, $row])->getDataType() === DataType::TYPE_FORMULA) {
                    throw new DriveAsixWorkbookException(
                        'Sort lokal hanya tersedia untuk rentang tanpa formula agar referensi tetap akurat.'
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function applySheetOperation(
        Spreadsheet $spreadsheet,
        array $operation,
        string $type,
        string $format
    ): void {
        if ($type === 'add_sheet') {
            $this->assertCanCreateSheet($spreadsheet);
            $title = $this->sheetTitle($operation['title'] ?? 'Sheet');
            $spreadsheet->addSheet(new Worksheet($spreadsheet, $title));

            return;
        }

        $worksheet = $this->sheet($spreadsheet, $operation['sheet'] ?? null);

        if ($type === 'rename_sheet') {
            $worksheet->setTitle($this->sheetTitle($operation['title'] ?? null));

            return;
        }

        if ($type === 'delete_sheet') {
            if ($spreadsheet->getSheetCount() <= 1) {
                throw new DriveAsixWorkbookException('Workbook harus memiliki sedikitnya satu sheet.');
            }
            $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($worksheet));

            return;
        }

        $this->assertCanCreateSheet($spreadsheet);
        $title = $this->sheetTitle(
            $operation['title'] ?? ($worksheet->getTitle().' Salinan')
        );
        $sourceIndex = $spreadsheet->getIndex($worksheet);
        $copy = clone $worksheet;
        $copy->setTitle($title);
        $spreadsheet->addSheet($copy, $sourceIndex + 1);
    }

    private function assertCanCreateSheet(Spreadsheet $spreadsheet): void
    {
        if ($spreadsheet->getSheetCount() >= self::MAX_SHEETS_PER_WORKBOOK) {
            throw new DriveAsixWorkbookException(
                'DriveASIX mendukung maksimal '.self::MAX_SHEETS_PER_WORKBOOK
                .' sheet dalam satu workbook.'
            );
        }
    }

    private function assertSheetCount(int $sheetCount): void
    {
        if ($sheetCount < 1) {
            throw new DriveAsixWorkbookException('Workbook tidak memiliki sheet.');
        }

        if ($sheetCount > self::MAX_SHEETS_PER_WORKBOOK) {
            throw new DriveAsixWorkbookException(
                'Workbook memiliki terlalu banyak sheet. Batas DriveASIX adalah '
                .self::MAX_SHEETS_PER_WORKBOOK.' sheet.'
            );
        }
    }

    private function boundedCellCost(int $rows, int $columns): int
    {
        $rows = max(1, $rows);
        $columns = max(1, $columns);

        if ($rows > intdiv(self::MAX_TOTAL_CELLS_PER_SAVE, $columns)) {
            return self::MAX_TOTAL_CELLS_PER_SAVE + 1;
        }

        return min(self::MAX_TOTAL_CELLS_PER_SAVE + 1, $rows * $columns);
    }

    private function sheet(Spreadsheet $spreadsheet, mixed $identifier): Worksheet
    {
        if (is_int($identifier) || (is_string($identifier) && ctype_digit($identifier))) {
            $index = (int) $identifier;
            if ($index >= 0 && $index < $spreadsheet->getSheetCount()) {
                return $spreadsheet->getSheet($index);
            }
        }

        if (is_string($identifier) && trim($identifier) !== '') {
            $sheet = $spreadsheet->getSheetByName($identifier);
            if ($sheet !== null) {
                return $sheet;
            }
        }

        throw new DriveAsixWorkbookException('Sheet yang dipilih tidak ditemukan.');
    }

    private function sheetTitle(mixed $title): string
    {
        $title = trim((string) $title);
        if ($title === '' || mb_strlen($title) > 31 || preg_match('/[\\\\\/?*:\[\]]/', $title)) {
            throw new DriveAsixWorkbookException('Nama sheet tidak valid atau lebih dari 31 karakter.');
        }

        return $title;
    }

    private function cellAddress(mixed $address, string $format = 'xlsx'): string
    {
        $address = strtoupper(trim((string) $address));
        if (preg_match('/^([A-Z]{1,3})([1-9][0-9]{0,6})$/', $address, $matches) !== 1) {
            throw new DriveAsixWorkbookException('Alamat sel tidak valid.');
        }

        $this->assertFormatBounds(
            Coordinate::columnIndexFromString($matches[1]),
            (int) $matches[2],
            $format
        );

        return $address;
    }

    private function range(mixed $range, string $format = 'xlsx'): string
    {
        $range = strtoupper(trim((string) $range));
        if (preg_match(
            '/^([A-Z]{1,3})([1-9][0-9]{0,6})(?::([A-Z]{1,3})([1-9][0-9]{0,6}))?$/',
            $range,
            $matches
        ) !== 1) {
            throw new DriveAsixWorkbookException('Rentang sel tidak valid.');
        }

        $endColumn = $matches[3] ?? $matches[1];
        $endRow = isset($matches[4]) ? (int) $matches[4] : (int) $matches[2];
        $this->assertFormatBounds(Coordinate::columnIndexFromString($matches[1]), (int) $matches[2], $format);
        $this->assertFormatBounds(Coordinate::columnIndexFromString($endColumn), $endRow, $format);

        return $matches[1].$matches[2].':'.$endColumn.$endRow;
    }

    private function column(mixed $column, string $format): string
    {
        if (is_numeric($column)) {
            $column = Coordinate::stringFromColumnIndex((int) $column);
        }

        $column = strtoupper(trim((string) $column));
        if (preg_match('/^[A-Z]{1,3}$/', $column) !== 1) {
            throw new DriveAsixWorkbookException('Kolom tidak valid.');
        }

        $this->assertFormatBounds(Coordinate::columnIndexFromString($column), 1, $format);

        return $column;
    }

    private function row(mixed $row, string $format): int
    {
        $row = $this->positiveInt($row, 'baris');
        $this->assertFormatBounds(1, $row, $format);

        return $row;
    }

    private function assertFormatBounds(int $column, int $row, string $format): void
    {
        $maxColumn = $format === 'xls' ? 256 : 16_384;
        $maxRow = $format === 'xls' ? 65_536 : 1_048_576;

        if ($column < 1 || $column > $maxColumn || $row < 1 || $row > $maxRow) {
            throw new DriveAsixWorkbookException(
                'Alamat berada di luar batas format '.strtoupper($format).'.'
            );
        }
    }

    private function assertRangeSize(string $range): void
    {
        $size = $this->rangeSize($range);

        if ($size > self::MAX_CELLS_IN_OPERATION) {
            throw new DriveAsixWorkbookException(
                'Satu operasi dibatasi maksimal '.number_format(self::MAX_CELLS_IN_OPERATION, 0, ',', '.')
                .' sel.'
            );
        }
    }

    private function rangeSize(string $range): int
    {
        [$start, $end] = Coordinate::rangeBoundaries($range);

        return ($end[0] - $start[0] + 1) * ($end[1] - $start[1] + 1);
    }

    private function positiveInt(mixed $value, string $label): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new DriveAsixWorkbookException(ucfirst($label).' tidak valid.');
        }

        return $value;
    }

    private function boundedCount(mixed $value): int
    {
        $value = $this->positiveInt($value, 'jumlah');
        if ($value > 1_000) {
            throw new DriveAsixWorkbookException('Satu operasi dibatasi maksimal 1.000 baris/kolom.');
        }

        return $value;
    }

    private function number(mixed $value, float $min, float $max, string $label): float
    {
        if (! is_numeric($value)) {
            throw new DriveAsixWorkbookException(ucfirst($label).' tidak valid.');
        }

        $number = (float) $value;
        if ($number < $min || $number > $max) {
            throw new DriveAsixWorkbookException(
                ucfirst($label)." harus antara {$min} dan {$max}."
            );
        }

        return $number;
    }

    private function argb(mixed $color): string
    {
        $color = strtoupper(ltrim(trim((string) $color), '#'));
        if (preg_match('/^(?:[0-9A-F]{6}|[0-9A-F]{8})$/', $color) !== 1) {
            throw new DriveAsixWorkbookException('Warna harus dalam format hex.');
        }

        return strlen($color) === 6 ? 'FF'.$color : $color;
    }

    private function assertFormulaSafe(string $formula): void
    {
        if (mb_strlen($formula) > 8_192) {
            throw new DriveAsixWorkbookException('Formula terlalu panjang.');
        }

        if (preg_match(self::BLOCKED_FORMULA_PATTERN, $formula) === 1) {
            throw new DriveAsixWorkbookException(
                'Formula yang memanggil file, jaringan, atau layanan eksternal tidak diizinkan.'
            );
        }
    }

    private function containsUnsafeFormula(Spreadsheet $spreadsheet): bool
    {
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            foreach ($worksheet->getCoordinates() as $address) {
                $cell = $worksheet->getCell($address);
                if ($cell->getDataType() === DataType::TYPE_FORMULA
                    && preg_match(self::BLOCKED_FORMULA_PATTERN, (string) $cell->getValue()) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function storeVersion(
        DriveAsixFile $file,
        string $sourcePath,
        string $format,
        string $revision,
        ?int $userId
    ): string {
        $directory = self::VERSION_PATH.'/'.$file->getKey();
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory($directory);
        $name = now()->format('Ymd_His_u').'_'
            .substr(str_replace('sha256:', '', $revision), 0, 12)
            .($userId ? '_u'.$userId : '')
            .'.'.$format;

        $stream = fopen($sourcePath, 'rb');
        if ($stream === false || ! $disk->put($directory.'/'.$name, $stream)) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new DriveAsixWorkbookException('Versi cadangan gagal dibuat; file asli tidak diubah.');
        }
        fclose($stream);

        $versions = collect($disk->files($directory))
            ->sortDesc()
            ->values();

        foreach ($versions->slice(self::VERSION_RETENTION) as $oldVersion) {
            $disk->delete($oldVersion);
        }

        return $directory.'/'.$name;
    }
}
