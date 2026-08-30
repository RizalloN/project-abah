<?php

namespace Tests\Feature;

use App\Support\ReportCacheVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

        $this->workbookPath = tempnam(sys_get_temp_dir(), 'brihc_reference_').'.xlsx';
        $spreadsheet = new Spreadsheet;
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

    public function test_it_syncs_compact_pdwk_reference_without_changing_brihc_pemasar(): void
    {
        $timestamp = now();
        $this->app['db']->table('brihc')->insert([
            ['uniqueid_brihc' => 'old-kaunit', 'pn' => '901', 'nama' => 'KA Unit Lama', 'jabatan' => 'KAUNIT', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['uniqueid_brihc' => 'old-pinca', 'pn' => '902', 'nama' => 'Pinca Lama', 'jabatan' => 'PINCA', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['uniqueid_brihc' => 'kept-mantri', 'pn' => '903', 'nama' => 'Mantri Tetap', 'jabatan' => 'MANTRI', 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
        $this->app['db']->table('brihc_pemasar')->insert([
            'uniqueid_namareport' => 'existing-pemasar',
            'completename' => 'Pemasar Tetap',
            'positiondesc' => 'KAUNIT',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $pdwkPath = tempnam(sys_get_temp_dir(), 'brihc_pdwk_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['KAUNIT'],
            ['PN', 'NAMA', 'JABATAN'],
            ['000101', 'KA Unit Baru', 'KAUNIT'],
            ['000102', 'MBM Baru', 'MBM'],
            [null, null, 'JABATAN'],
            ['PINCA'],
            ['PN', 'NAMA', 'JABATAN'],
            ['000103', 'BOH Baru', 'BOH'],
            ['MBM'],
            ['PN', 'NAMA', 'JABATAN'],
            ['000104', 'RMBH Baru', 'RMBH'],
            ['000105', 'SBOH Baru', 'SBOH'],
        ]);
        (new Xlsx($spreadsheet))->save($pdwkPath);

        try {
            $this->artisan('reference:sync-brihc', ['file' => $pdwkPath])
                ->expectsOutputToContain('"source_format": "pdwk"')
                ->assertExitCode(0);
        } finally {
            @unlink($pdwkPath);
        }

        $this->assertDatabaseMissing('brihc', ['pn' => '901']);
        $this->assertDatabaseMissing('brihc', ['pn' => '902']);
        $this->assertDatabaseHas('brihc', ['pn' => '101', 'nama' => 'KA Unit Baru', 'jabatan' => 'KAUNIT']);
        $this->assertDatabaseHas('brihc', ['pn' => '102', 'nama' => 'MBM Baru', 'jabatan' => 'MBM']);
        $this->assertDatabaseHas('brihc', ['pn' => '103', 'nama' => 'BOH Baru', 'jabatan' => 'PINCA']);
        $this->assertDatabaseHas('brihc', ['pn' => '104', 'nama' => 'RMBH Baru', 'jabatan' => 'RMBH']);
        $this->assertDatabaseHas('brihc', ['pn' => '105', 'nama' => 'SBOH Baru', 'jabatan' => 'MBM']);
        $this->assertDatabaseHas('brihc', ['pn' => '903', 'jabatan' => 'MANTRI']);
        $this->assertDatabaseHas('brihc_pemasar', ['uniqueid_namareport' => 'existing-pemasar']);
        $this->assertSame(1, $this->app['db']->table('brihc_pemasar')->count());
    }

    public function test_it_reconciles_the_three_sheet_rm_roster_without_losing_a_second_role_for_the_same_pn(): void
    {
        $timestamp = now();
        $this->app['db']->table('brihc')->insert([
            ['uniqueid_brihc' => 'stale-small', 'pn' => '9001', 'nama' => 'RM Alih Fungsi', 'jabatan' => 'RM BISNIS KECIL', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['uniqueid_brihc' => 'kept-mantri', 'pn' => '9002', 'nama' => 'Mantri Tetap', 'jabatan' => 'MANTRI', 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
        $this->app['db']->table('brihc_pemasar')->insert([
            ['uniqueid_namareport' => 'stale-small', 'pernr' => '9001', 'completename' => 'RM Alih Fungsi', 'positiondesc' => 'RM BISNIS KECIL', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['uniqueid_namareport' => 'kept-mantri', 'pernr' => '9002', 'completename' => 'Mantri Tetap', 'positiondesc' => 'MANTRI', 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);

        $listPath = tempnam(sys_get_temp_dir(), 'list_rm_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $consumer = $spreadsheet->getActiveSheet();
        $consumer->setTitle('Konsumer');
        $consumer->fromArray([
            ['NO', 'KANCA', 'BC', 'UKER', 'SEGMEN', 'PN PENGELOLA SINGLEPN'],
            [1, 'KC Madiun', '#REF!', 'KC Madiun', 'KPR', '00080522 - Mochamad Taufik Hidayat'],
        ]);
        $micro = $spreadsheet->createSheet();
        $micro->setTitle('RM Mikro');
        $micro->fromArray([
            ['PN', 'NAMA', 'BC UKER', 'UKER', 'KANCA'],
            ['00335871', 'Aulia Rika Ramadhani', '70', '00070--KC Ponorogo', 'PONOROGO'],
        ]);
        $small = $spreadsheet->createSheet();
        $small->setTitle('RM Small');
        $small->fromArray([
            ['NO', 'KODE KANCA', 'KANCA', 'KODE UKER', 'UKER', 'PN', 'NAMA RM', 'JG', 'STATUS RM'],
            [1, '70', 'Ponorogo', '2204', 'KCP SUDIRMAN PONOROGO', '00335871', 'Aulia Rika Ramadhani', 'JG07', 'RM Small'],
            [2, '45', 'Madiun', '552', 'KCP CARUBAN', '00266178', 'Siska Chandra Ariana', 'JG05', 'RM Small'],
        ]);
        (new Xlsx($spreadsheet))->save($listPath);

        try {
            $command = app(\App\Console\Commands\SyncBrihcReferenceCommand::class);
            $method = new \ReflectionMethod($command, 'readSourceRows');
            $source = $method->invoke($command, $listPath);
            $this->assertSame(4, count($source['rows']));

            $this->artisan('reference:sync-brihc', ['file' => $listPath, '--dry-run' => true])
                ->expectsOutputToContain('"source_format": "list_rm"')
                ->assertExitCode(0);
            $this->assertDatabaseHas('brihc', ['pn' => '9001', 'jabatan' => 'RM BISNIS KECIL']);

            $this->artisan('reference:sync-brihc', ['file' => $listPath])
                ->expectsOutputToContain('"source_format": "list_rm"')
                ->assertExitCode(0);
        } finally {
            @unlink($listPath);
        }

        $this->assertDatabaseMissing('brihc', ['pn' => '9001', 'jabatan' => 'RM BISNIS KECIL']);
        $this->assertDatabaseMissing('brihc_pemasar', ['pernr' => '9001', 'positiondesc' => 'RM BISNIS KECIL']);
        $this->assertDatabaseHas('brihc', ['pn' => '9002', 'jabatan' => 'MANTRI']);
        $this->assertDatabaseHas('brihc', ['pn' => '80522', 'nama' => 'Mochamad Taufik Hidayat', 'jabatan' => 'RM BISNIS KONSUMER - KPR']);
        $this->assertDatabaseHas('brihc', ['pn' => '335871', 'jabatan' => 'RM MIKRO']);
        $this->assertDatabaseHas('brihc', ['pn' => '335871', 'jabatan' => 'RM BISNIS KECIL']);
        $this->assertDatabaseHas('brihc_pemasar', [
            'pernr' => '266178',
            'completename' => 'Siska Chandra Ariana',
            'psadesc' => 'KC Madiun',
            'orgdesc' => 'KCP CARUBAN',
            'bc' => '552',
            'positiondesc' => 'RM BISNIS KECIL',
        ]);
    }
}
