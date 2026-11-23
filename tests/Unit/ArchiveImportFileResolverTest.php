<?php

namespace Tests\Unit;

use App\Services\Import\ArchiveImportFileResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArchiveImportFileResolverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        $this->tempDir = storage_path('framework/testing/archive_resolver_' . Str::random(8));
        File::ensureDirectoryExists($this->tempDir, 0750, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_single_file_returns_immediately_without_requiring_db_or_disk_scan(): void
    {
        $resolver = new ArchiveImportFileResolver();
        $files = [
            ['name' => 'single_report.csv', 'path' => $this->tempDir . DIRECTORY_SEPARATOR . 'nonexistent.csv'],
        ];

        $resolved = $resolver->resolve($files);

        $this->assertSame($files[0], $resolved);
    }

    public function test_empty_files_returns_null(): void
    {
        $resolver = new ArchiveImportFileResolver();

        $this->assertNull($resolver->resolve([]));
    }

    public function test_multi_file_filters_macos_junk_and_zero_byte_files_to_pick_valid_file(): void
    {
        $validPath = $this->createFile('report_valid.csv', "POSISI,NAMA_KANCA,SALDO\n2026-11-22,KC Madiun,100\n");
        $emptyPath = $this->createFile('empty_copy.csv', '');
        $macOsMetaPath = $this->createFile('__MACOSX/._report_valid.csv', 'garbage');
        $dotUnderscorePath = $this->createFile('._sidecar.csv', 'garbage');

        $resolver = new ArchiveImportFileResolver();
        $resolved = $resolver->resolve([
            ['name' => '._report_valid.csv', 'path' => $macOsMetaPath],
            ['name' => '._sidecar.csv', 'path' => $dotUnderscorePath],
            ['name' => 'empty_copy.csv', 'path' => $emptyPath],
            ['name' => 'report_valid.csv', 'path' => $validPath],
        ]);

        $this->assertSame([
            'name' => 'report_valid.csv',
            'path' => $validPath,
        ], $resolved);
    }

    public function test_multi_file_matches_merchant_vs_qris_detail_by_report_and_table_name(): void
    {
        $merchantPath = $this->createFile(
            '1794791734_JUMLAH_MERCHANT_DETAIL_R_ALL.csv',
            "POSISI|NAMA_KANCA|NAMA_UKER|NOREK\n2026-11-16|KC Madiun|UNIT A|123\n"
        );
        $qrisPath = $this->createFile(
            '1794791740_JUMLAH_MERCHANT_QRIS_DETAIL_R_ALL.csv',
            "POSISI|MBDESC|BRDESC|MID\n2026-11-16|KC Madiun|UNIT A|999\n"
        );

        $files = [
            ['name' => basename($qrisPath), 'path' => $qrisPath],
            ['name' => basename($merchantPath), 'path' => $merchantPath],
        ];

        $resolver = new ArchiveImportFileResolver();

        $merchantReport = (object) [
            'nama_report' => 'Jumlah Merchant Detail',
            'table_name' => 'jumlah_merchant_detail',
        ];
        $this->assertSame(
            $merchantPath,
            $resolver->resolve($files, $merchantReport, 'jumlah_merchant_detail')['path'] ?? null
        );

        $qrisReport = (object) [
            'nama_report' => 'Jumlah Merchant Qris Detail',
            'table_name' => 'jumlah_merchant_qris_detail',
        ];
        $this->assertSame(
            $qrisPath,
            $resolver->resolve($files, $qrisReport, 'jumlah_merchant_qris_detail')['path'] ?? null
        );
    }

    public function test_multi_file_matches_brimo_fin_vs_rpt_v2_by_report_and_table_name(): void
    {
        $finPath = $this->createFile(
            '1794791763_USER_BRIMO_FIN_DETAIL_R_ALL.csv',
            "POSISI|MBDESC|JUMLAH\n2026-11-16|KC Madiun|10\n"
        );
        $rptPath = $this->createFile(
            '1794791755_USER_BRIMO_RPT_V2_DETAIL_R_ALL.csv',
            "POSISI|MBDESC|JUMLAH\n2026-11-16|KC Madiun|20\n"
        );

        $files = [
            ['name' => basename($rptPath), 'path' => $rptPath],
            ['name' => basename($finPath), 'path' => $finPath],
        ];

        $resolver = new ArchiveImportFileResolver();

        $finReport = (object) [
            'nama_report' => 'User Brimo Fin',
            'table_name' => 'user_brimo_fin',
        ];
        $this->assertSame(
            $finPath,
            $resolver->resolve($files, $finReport, 'user_brimo_fin')['path'] ?? null
        );

        $rptReport = (object) [
            'nama_report' => 'User Brimo RPT v2',
            'table_name' => 'user_brimo_rpt_v2',
        ];
        $this->assertSame(
            $rptPath,
            $resolver->resolve($files, $rptReport, 'user_brimo_rpt_v2')['path'] ?? null
        );
    }

    public function test_multi_file_prefers_csv_with_matching_db_headers_over_plain_text_matching_filename(): void
    {
        Schema::create('jumlah_merchant_detail', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->string('posisi')->nullable();
            $table->string('nama_kanca')->nullable();
            $table->string('nama_uker')->nullable();
            $table->string('norek')->nullable();
        });

        $readmePath = $this->createFile(
            'readme_jumlah_merchant_detail.txt',
            "Panduan singkat upload jumlah merchant detail, harap dibaca.\nPastikan periode sudah sesuai.\n"
        );
        $dataPath = $this->createFile(
            'export_data.csv',
            "POSISI|NAMA_KANCA|NAMA_UKER|NOREK\n2026-11-16|KC Madiun|UNIT A|00123\n"
        );

        $resolver = new ArchiveImportFileResolver();
        $report = (object) [
            'nama_report' => 'Jumlah Merchant Detail',
            'table_name' => 'jumlah_merchant_detail',
        ];

        $resolved = $resolver->resolve([
            ['name' => basename($readmePath), 'path' => $readmePath],
            ['name' => basename($dataPath), 'path' => $dataPath],
        ], $report, 'jumlah_merchant_detail');

        $this->assertSame($dataPath, $resolved['path'] ?? null);
    }

    public function test_multi_file_prefers_csv_with_more_matching_db_columns_when_names_are_generic(): void
    {
        Schema::create('jumlah_merchant_detail', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->string('posisi')->nullable();
            $table->string('nama_kanca')->nullable();
            $table->string('nama_uker')->nullable();
            $table->string('norek')->nullable();
        });

        $betterMatchPath = $this->createFile(
            'export_a.csv',
            "POSISI|NAMA_KANCA|NAMA_UKER|NOREK\n2026-11-16|KC Madiun|UNIT A|00123\n"
        );
        $partialMatchPath = $this->createFile(
            'export_b.csv',
            "POSISI|COL_X|COL_Y|COL_Z\n2026-11-16|X|Y|Z\n"
        );

        $resolver = new ArchiveImportFileResolver();
        $report = (object) [
            'nama_report' => 'Jumlah Merchant Detail',
            'table_name' => 'jumlah_merchant_detail',
        ];

        $resolved = $resolver->resolve([
            ['name' => basename($partialMatchPath), 'path' => $partialMatchPath],
            ['name' => basename($betterMatchPath), 'path' => $betterMatchPath],
        ], $report, 'jumlah_merchant_detail');

        $this->assertSame($betterMatchPath, $resolved['path'] ?? null);
    }

    public function test_multi_file_genuinely_ambiguous_returns_null_for_safe_fallback(): void
    {
        Schema::create('jumlah_merchant_detail', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->string('posisi')->nullable();
            $table->string('nama_kanca')->nullable();
            $table->string('nama_uker')->nullable();
        });

        $part1 = $this->createFile(
            'batch_alpha.csv',
            "POSISI|NAMA_KANCA|NAMA_UKER\n2026-11-16|KC Madiun|UNIT A\n"
        );
        $part2 = $this->createFile(
            'batch_beta.csv',
            "POSISI|NAMA_KANCA|NAMA_UKER\n2026-11-16|KC Ngawi|UNIT B\n"
        );

        $resolver = new ArchiveImportFileResolver();
        $report = (object) [
            'nama_report' => 'Jumlah Merchant Detail',
            'table_name' => 'jumlah_merchant_detail',
        ];

        $resolved = $resolver->resolve([
            ['name' => basename($part1), 'path' => $part1],
            ['name' => basename($part2), 'path' => $part2],
        ], $report, 'jumlah_merchant_detail');

        $this->assertNull($resolved);
    }

    private function createFile(string $relativePath, string $contents): string
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        File::ensureDirectoryExists(dirname($path), 0750, true);
        File::put($path, $contents);

        return $path;
    }
}
