<?php

namespace Tests\Feature;

use App\Support\ReportCacheVersion;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncBrihcReferenceCommandTest extends TestCase
{
    private string $workbookPath;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('brihc');
        Schema::dropIfExists('brihc_pemasar');

        Schema::create('brihc', function (Blueprint $table): void {
            $table->string('uniqueid_brihc')->primary();
            $table->string('pn')->nullable();
            $table->string('nama')->nullable();
            $table->string('jabatan')->nullable();
            $table->timestamps();
        });

        Schema::create('brihc_pemasar', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->string('completename')->nullable();
            $table->string('pernr')->nullable();
            $table->string('sex')->nullable();
            $table->string('age')->nullable();
            $table->string('esgdesc')->nullable();
            $table->string('padesc')->nullable();
            $table->string('psadesc')->nullable();
            $table->string('orgdesc')->nullable();
            $table->string('positiondesc')->nullable();
            $table->string('mkj')->nullable();
            $table->string('descprogrammasuk')->nullable();
            $table->string('jobgrade')->nullable();
            $table->string('bc')->nullable();
            $table->string('pn_mantri')->nullable();
            $table->string('status')->nullable();
            $table->string('jg')->nullable();
            $table->timestamps();
        });

        $this->workbookPath = tempnam(sys_get_temp_dir(), 'brihc_reference_') . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['PERNR', 'GENDER', 'COMPLETENAME', 'KELOMPOK JABATAN', 'JG', 'MCTEXT', 'PADESC', 'AGE', 'ESELON', 'ORGDESC', 'CORP. TITLE', 'PSADESC', 'DESCPROGRAMMASUK', 'KODE BRANCH', 'ESGDESC', 'MKJ'],
            ['001234', 'L', 'Mantri Baru', 'Mantri', 'JG06', null, 'Region 13 Malang', 30, null, 'UNIT DOLOPO', null, 'KC Madiun', 'PDP', '6347', 'PT', '3 tahun'],
            ['005678', 'P', 'Mantri Briguna Baru', 'Mantri Briguna', 'JG07', null, 'Region 13 Malang', 31, null, 'UNIT MLARAK', null, 'KC Ponorogo', 'PDP', '6433', 'PT', '2 tahun'],
        ]);
        (new Xlsx($spreadsheet))->save($this->workbookPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->workbookPath);
        Schema::dropIfExists('brihc');
        Schema::dropIfExists('brihc_pemasar');

        parent::tearDown();
    }

    public function test_it_replaces_mantri_reference_and_preserves_decision_makers(): void
    {
        $timestamp = now();
        $this->app['db']->table('brihc')->insert([
            ['uniqueid_brihc' => 'old-kaunit', 'pn' => '999', 'nama' => 'KA Unit Tetap', 'jabatan' => 'KAUNIT', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['uniqueid_brihc' => 'old-mantri', 'pn' => '111', 'nama' => 'Mantri Lama', 'jabatan' => 'MANTRI', 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
        $this->app['db']->table('brihc_pemasar')->insert([
            ['uniqueid_namareport' => 'old-mantri', 'completename' => 'Mantri Lama', 'positiondesc' => 'ASSOCIATE MANTRI', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['uniqueid_namareport' => 'other-role', 'completename' => 'RM Tetap', 'positiondesc' => 'RM MICRO', 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);

        $cacheVersionBefore = ReportCacheVersion::get('pinjaman');

        $this->artisan('reference:sync-brihc', ['file' => $this->workbookPath])
            ->assertExitCode(0);

        $this->assertDatabaseHas('brihc', ['pn' => '999', 'jabatan' => 'KAUNIT']);
        $this->assertDatabaseMissing('brihc', ['pn' => '111']);
        $this->assertDatabaseHas('brihc', ['pn' => '1234', 'nama' => 'Mantri Baru', 'jabatan' => 'MANTRI']);
        $this->assertDatabaseHas('brihc', ['pn' => '5678', 'nama' => 'Mantri Briguna Baru', 'jabatan' => 'MANTRI BRIGUNA']);
        $this->assertDatabaseMissing('brihc_pemasar', ['uniqueid_namareport' => 'old-mantri']);
        $this->assertDatabaseHas('brihc_pemasar', ['uniqueid_namareport' => 'other-role']);
        $this->assertDatabaseHas('brihc_pemasar', ['pernr' => '1234', 'pn_mantri' => '1234', 'orgdesc' => 'UNIT DOLOPO']);
        $this->assertSame($cacheVersionBefore + 1, ReportCacheVersion::get('pinjaman'));
    }

    public function test_dry_run_does_not_change_reference_rows(): void
    {
        $timestamp = now();
        $this->app['db']->table('brihc')->insert([
            'uniqueid_brihc' => 'old-mantri',
            'pn' => '111',
            'nama' => 'Mantri Lama',
            'jabatan' => 'MANTRI',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $this->artisan('reference:sync-brihc', ['file' => $this->workbookPath, '--dry-run' => true])
            ->expectsOutputToContain('"dry_run": true')
            ->assertExitCode(0);

        $this->assertDatabaseHas('brihc', ['pn' => '111', 'jabatan' => 'MANTRI']);
        $this->assertDatabaseMissing('brihc', ['pn' => '1234']);
    }
}
