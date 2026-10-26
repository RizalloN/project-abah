<?php

namespace App\Http\Controllers\Import;

use App\Services\Import\ImportProgressService;
use App\Services\Import\MauBrimoImportPolicy;
use App\Support\ManagedReportManagementService;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ImportMauBrimoController extends ImportFileBrimoController
{
    public function preview(Request $request)
    {
        $this->report();
        $request->validate(['file_path' => 'required|string', 'delimiter' => 'nullable|string']);
        $filePath = $this->authorizeImportSourceFile($request->string('file_path')->toString());
        $currentDelimiter = $this->delimiter($filePath, $request->input('delimiter') ?? 'auto');
        $policy = app(MauBrimoImportPolicy::class);
        $headers = $previewData = $uniqueValues = [];
        $map = null;

        foreach ($this->records($filePath, $currentDelimiter) as [$line, $row]) {
            if ($map === null) {
                $headers = $row;
                $map = $policy->headerMap($headers);

                continue;
            }
            $normalized = $this->eligibleRow($row, $headers, $map, $line);
            if ($normalized === null) {
                continue;
            }
            $display = $row;
            foreach ($map as $column => $index) {
                $display[$index] = (string) $normalized[$column];
                if (count($uniqueValues[$index] ?? []) < 400 || $column === 'mbdesc') {
                    $uniqueValues[$index][$display[$index]] = true;
                }
            }
            if (count($previewData) < 100) {
                $previewData[] = $display;
            }
        }
        $this->assertRowsAvailable($map !== null && $previewData !== []);
        $formattedUniqueValues = [];
        foreach ($uniqueValues as $index => $values) {
            $formattedUniqueValues[$index] = array_keys($values);
            sort($formattedUniqueValues[$index]);
        }
        $initialArea6Selections = $policy->initialSelections($headers, $formattedUniqueValues);
        session(['final_import_path' => $filePath, 'import_display_to_source_map' => range(0, count($headers) - 1)]);

        return view('import.preview', [
            'headers' => $headers, 'previewData' => $previewData, 'filePath' => $filePath,
            'formattedUniqueValues' => $formattedUniqueValues,
            'filterableColumnIndices' => array_keys($formattedUniqueValues),
            'currentDelimiter' => $currentDelimiter,
            'processRoute' => route('import.brimo.process'),
            'previewRoute' => route('import.brimo.preview'),
            'filterOptionsRoute' => route('import.preview.filter-options'),
            'filteredRowsRoute' => route('import.preview.filtered-rows'),
            'warmIndexRoute' => route('import.preview.warm-index'),
            'area6ColumnHints' => ['MBDESC'], 'initialArea6Selections' => $initialArea6Selections,
            'warmPreviewIndexOnLoad' => false, 'initialFilterOptionsAreComplete' => false,
            'disableFilterOptionsLocalCache' => false, 'portalFilterDropdowns' => true,
            'hidePreviewRowsUntilJs' => true, 'backRoute' => route('import.index'),
        ]);
    }

    public function previewFilterOptions(Request $request)
    {
        $request->validate(['column_index' => 'required|integer|min:0']);

        return $this->filteredPreview($request, (int) $request->input('column_index'));
    }

    public function previewFilteredRows(Request $request)
    {
        return $this->filteredPreview($request);
    }

    private function filteredPreview(Request $request, ?int $optionsColumn = null)
    {
        $this->report();
        $request->validate([
            'file_path' => 'required|string', 'delimiter' => 'nullable|string',
            'active_filters_json' => 'nullable|json', 'limit' => 'nullable|integer|min:1|max:200',
        ]);
        $filePath = $this->authorizeImportSourceFile($request->string('file_path')->toString());
        $delimiter = $this->delimiter($filePath, $request->input('delimiter') ?? 'auto');
        $filters = json_decode($request->input('active_filters_json') ?? '{}', true) ?? [];
        $this->releaseSessionLockIfNeeded();
        $map = null;
        $headers = $rows = $values = [];
        $matched = 0;
        $limit = (int) ($request->input('limit') ?? 100);
        foreach ($this->records($filePath, $delimiter) as [$line, $row]) {
            if ($map === null) {
                $headers = $row;
                $map = app(MauBrimoImportPolicy::class)->headerMap($headers);
                $this->assertFilters($filters, count($headers));
                if ($optionsColumn !== null) {
                    if ($optionsColumn >= count($headers)) {
                        throw ValidationException::withMessages(['column_index' => 'Kolom filter MAU BRIMO tidak valid.']);
                    }
                    unset($filters[$optionsColumn]);
                }

                continue;
            }
            $normalized = $this->eligibleRow($row, $headers, $map, $line);
            if ($normalized === null || ! $this->passesFilters($row, $normalized, $map, $filters)) {
                continue;
            }
            foreach ($map as $column => $index) {
                $row[$index] = (string) $normalized[$column];
            }
            if ($optionsColumn !== null) {
                $values[$row[$optionsColumn]] = true;
            } else {
                $matched++;
                if (count($rows) < $limit) {
                    $rows[] = $row;
                }
            }
        }
        if ($map === null) {
            throw ValidationException::withMessages(['file' => 'Header MAU BRIMO tidak tersedia.']);
        }
        if ($optionsColumn !== null) {
            $values = array_map('strval', array_keys($values));
            sort($values, SORT_NATURAL | SORT_FLAG_CASE);

            return response()->json(['status' => 'success', 'values' => $values, 'cached' => false]);
        }

        return response()->json([
            'status' => 'success', 'rows' => $rows, 'total_matched' => $matched,
            'returned_rows' => count($rows), 'truncated' => $matched > count($rows), 'source' => 'mau-area6',
        ]);
    }

    public function processImport(Request $request)
    {
        $report = $this->report();
        $request->validate([
            'file_path' => 'required|string', 'selected_columns' => 'required|array|min:1',
            'selected_columns.*' => 'integer|min:0', 'active_filters_json' => 'nullable|json',
            'delimiter' => 'nullable|string',
        ]);
        $filePath = $this->authorizeImportSourceFile($request->string('file_path')->toString());
        $delimiter = $this->delimiter($filePath, $request->input('delimiter') ?? 'auto');
        $filters = json_decode($request->input('active_filters_json') ?? '{}', true) ?? [];
        $policy = app(MauBrimoImportPolicy::class);
        $headers = $rows = [];
        $map = null;
        $sourceRows = 0;
        $timestamp = now();

        foreach ($this->records($filePath, $delimiter) as [$line, $row]) {
            if ($map === null) {
                $headers = $row;
                $map = $policy->headerMap($headers);
                $policy->assertSelectedColumns($headers, $request->input('selected_columns'));
                $this->assertFilters($filters, count($headers));

                continue;
            }
            $sourceRows++;
            $normalized = $this->eligibleRow($row, $headers, $map, $line);
            if ($normalized === null || ! $this->passesFilters($row, $normalized, $map, $filters)) {
                continue;
            }
            $identity = $policy->identity($normalized);
            if (isset($rows[$identity])) {
                throw ValidationException::withMessages(['file' => "Baris {$line}: dimensi MAU BRIMO duplikat di dalam sumber."]);
            }
            $rows[$identity] = $normalized + ['uniqueid_namareport' => $identity, 'created_at' => $timestamp, 'updated_at' => $timestamp];
        }
        $this->assertRowsAvailable($map !== null && $rows !== []);
        $this->releaseSessionLockIfNeeded();
        $lock = Cache::store(config('import.cache_store', 'file') ?: config('cache.default'))->lock('import:mau-brimo:write', 180);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['file' => 'Import MAU BRIMO lain masih berjalan. Tunggu sampai selesai.']);
        }
        $progress = app(ImportProgressService::class);
        $jobId = null;
        $committed = false;
        try {
            foreach (array_chunk(array_keys($rows), 500) as $ids) {
                if (DB::table(MauBrimoImportPolicy::TABLE)->whereIn('uniqueid_namareport', $ids)->exists()) {
                    return response()->json(['status' => 'warning', 'duplicate_detected' => true, 'title' => 'Data sudah diimport', 'text' => 'Dimensi dan posisi MAU BRIMO ini sudah tersimpan.'], 409);
                }
            }
            $jobId = $progress->createJob([
                'id_report' => $report->id_report, 'file_name' => basename($filePath), 'folder_path' => dirname($filePath),
                'status' => 'processing', 'total_files' => count($rows), 'total_success' => 0, 'total_failed' => 0,
                'created_by' => auth()->id() ?? 1, 'job_content_hash' => hash_file('sha256', $filePath),
                'job_context' => ['controller' => static::class, 'mode' => 'mau_brimo_import', 'table_name' => MauBrimoImportPolicy::TABLE,
                    'file_path' => $filePath, 'source_rows' => $sourceRows, 'area6_filter_column' => 'MBDESC'],
            ]);
            $progress->markProcessing($jobId, ['phase' => 'loading', 'message' => 'Mengimpor MAU BRIMO Area 6 berdasarkan MBDESC.', 'total_rows' => count($rows)]);
            DB::transaction(function () use ($rows): void {
                foreach (array_chunk(array_values($rows), 500) as $chunk) {
                    DB::table(MauBrimoImportPolicy::TABLE)->insert($chunk);
                }
                $actualCount = $actualCif = $actualAccounts = 0;
                foreach (array_chunk(array_keys($rows), 500) as $ids) {
                    $actual = DB::table(MauBrimoImportPolicy::TABLE)->whereIn('uniqueid_namareport', $ids)
                        ->selectRaw('COUNT(*) as total, SUM(jumlah_cifno) as cif, SUM(jumlah_rek_fin) as accounts')->first();
                    $actualCount += (int) $actual->total;
                    $actualCif += (int) $actual->cif;
                    $actualAccounts += (int) $actual->accounts;
                }
                if ($actualCount !== count($rows) || $actualCif !== array_sum(array_column($rows, 'jumlah_cifno'))
                    || $actualAccounts !== array_sum(array_column($rows, 'jumlah_rek_fin'))) {
                    throw new RuntimeException('Rekonsiliasi jumlah baris atau angka MAU BRIMO tidak sesuai sumber.');
                }
            });
            $committed = true;
            ManagedReportManagementService::invalidateTableCache(MauBrimoImportPolicy::TABLE);
            $progress->markCompleted($jobId, count($rows), 0, count($rows), ['percent' => 100, 'processed_rows' => count($rows), 'message' => 'Import MAU BRIMO Area 6 selesai dan terverifikasi.']);

            return response()->json([
                'status' => 'success', 'title' => 'Berhasil!', 'text' => count($rows).' baris MAU BRIMO Area 6 berhasil diimport.',
                'job_id' => $jobId, 'total_success' => count($rows), 'total_failed' => 0,
                'source_rows' => $sourceRows, 'filtered_rows' => $sourceRows - count($rows),
            ]);
        } catch (Throwable $exception) {
            if ($jobId !== null) {
                $progress->markFailed($jobId, 'Import MAU BRIMO gagal: '.$exception->getMessage(), $committed ? count($rows) : 0, 0, $committed ? 'failed_partial' : 'failed');
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function report(): object
    {
        $report = DB::table('nama_report')->where('id_report', session('active_id_report'))->first();
        if ($report === null || $report->table_name !== MauBrimoImportPolicy::TABLE || ! Schema::hasTable(MauBrimoImportPolicy::TABLE)) {
            throw ValidationException::withMessages(['id_report' => 'Konfigurasi tabel import MAU BRIMO tidak tersedia.']);
        }

        return $report;
    }

    private function delimiter(string $filePath, string $requested): string
    {
        $delimiter = $requested === 'auto' ? $this->smartDetectCsvDelimiter($filePath, [',', ';', "\t", '|']) : $requested;
        if (! in_array($delimiter, [',', ';', "\t", '|'], true)) {
            throw ValidationException::withMessages(['delimiter' => 'Delimiter MAU BRIMO tidak valid.']);
        }

        return $delimiter;
    }

    private function records(string $path, string $delimiter): Generator
    {
        $handle = fopen($path, 'rb');
        try {
            $line = 0;
            while (($text = fgets($handle)) !== false) {
                $line++;
                $row = $this->smartParseCsvLine($text, $delimiter);
                if ($row !== [] && count(array_filter($row, fn ($cell) => trim((string) $cell) !== '')) > 0) {
                    yield [$line, $row];
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function eligibleRow(array $row, array $headers, array $map, int $line): ?array
    {
        $policy = app(MauBrimoImportPolicy::class);
        if (! $policy->belongsToArea6($row[$map['mbdesc']] ?? null)) {
            return null;
        }
        if (count($row) !== count($headers)) {
            throw ValidationException::withMessages(['file' => "Baris {$line}: jumlah kolom MAU BRIMO tidak sesuai header."]);
        }

        return $policy->normalizeRow($row, $map, $line);
    }

    private function assertRowsAvailable(bool $available): void
    {
        if (! $available) {
            throw ValidationException::withMessages(['file' => 'Tidak ada baris MAU BRIMO untuk empat KC Area 6 yang sesuai filter.']);
        }
    }

    private function assertFilters(mixed $filters, int $columnCount): void
    {
        if (! is_array($filters)) {
            throw ValidationException::withMessages(['active_filters_json' => 'Filter MAU BRIMO tidak valid.']);
        }
        foreach ($filters as $index => $values) {
            if (! ctype_digit((string) $index) || (int) $index >= $columnCount || ! is_array($values)
                || count(array_filter($values, fn ($value) => ! is_scalar($value) && $value !== null)) > 0) {
                throw ValidationException::withMessages(['active_filters_json' => 'Kolom atau nilai filter MAU BRIMO tidak valid.']);
            }
        }
    }

    private function passesFilters(array $row, array $normalized, array $map, array $filters): bool
    {
        foreach ($map as $column => $index) {
            $row[$index] = (string) $normalized[$column];
        }
        foreach ($filters as $index => $values) {
            if ($values === [] || ! in_array(trim((string) ($row[$index] ?? '')), array_map(fn ($value) => trim((string) $value), $values), true)) {
                return false;
            }
        }

        return true;
    }
}
