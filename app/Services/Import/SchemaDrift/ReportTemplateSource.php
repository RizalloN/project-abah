<?php

namespace App\Services\Import\SchemaDrift;

use App\Http\Controllers\Import\Concerns\AuthorizesSessionImportStorageFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ReportTemplateSource
{
    use AuthorizesSessionImportStorageFiles;

    private const COLUMNS = [
        'input_rekanan' => ['perusahaan_anak', 'rekanan_level_1', 'rekanan_level_2', 'status_nasabah', 'cif', 'produk_1', 'produk_2', 'produk_3'],
        'bod_boc' => ['instansi', 'bod_boc', 'nama_nasabah', 'ket_nasabah', 'cif', 'fasilitas_1', 'fasilitas_2', 'fasilitas_3'],
    ];

    public static function columns(string $table): array
    {
        return self::COLUMNS[$table] ?? [];
    }

    public static function sourceTargets(string $table, array $headers): array
    {
        $columns = array_fill_keys(self::columns($table), true);

        return array_filter(array_map(static function ($header) use ($table, $columns): ?string {
            $key = strtolower(trim((string) $header));
            if ($table === 'bod_boc' && $key === 'bod/boc') {
                $key = 'bod_boc';
            }

            return isset($columns[$key]) ? $key : null;
        }, $headers));
    }

    public function store(string $table, UploadedFile $file): void
    {
        $path = $file->store('report_template_imports');
        if (!is_string($path) || $path === '') {
            throw new RuntimeException('File sumber template gagal disimpan.');
        }
        $key = $table.'_preview_path';
        $previous = session($key);
        session([$key => $path]);
        if (is_string($previous) && str_starts_with($previous, 'report_template_imports/')) {
            Storage::delete($previous);
        }
    }

    public function authorizedPath(string $table): string
    {
        [, $path] = $this->authorizeSessionImportStorageFile(null, $table.'_preview_path', ['report_template_imports'], ['xlsx', 'xls']);

        return $path;
    }

    public function release(string $table): void
    {
        Storage::delete((string) session($table.'_preview_path'));
        session()->forget($table.'_preview_path');
    }
}
