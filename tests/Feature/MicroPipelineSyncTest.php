<?php

namespace Tests\Feature;

use App\Services\Reports\MicroPipelineSyncService;
use App\Support\LandingMicroPipelineService;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class MicroPipelineSyncTest extends TestCase
{
    private string $workbookPath;
    private string $slikWorkbookPath;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('micro_pipeline_syncs');
        Schema::dropIfExists('micro_pipeline_records');
        $migration = require database_path('migrations/2026_08_31_140000_create_micro_pipeline_tables.php');
        $migration->up();

        $this->workbookPath = tempnam(sys_get_temp_dir(), 'micro_pipeline_').'.xlsx';
        $this->slikWorkbookPath = tempnam(sys_get_temp_dir(), 'micro_pipeline_slik_').'.xlsx';
        $this->writeWorkbook();
        $this->writeSlikWorkbook();
    }

    protected function tearDown(): void
    {
        if (isset($this->workbookPath) && is_file($this->workbookPath)) {
            @unlink($this->workbookPath);
        }
        if (isset($this->slikWorkbookPath) && is_file($this->slikWorkbookPath)) {
            @unlink($this->slikWorkbookPath);
        }
        Schema::dropIfExists('micro_pipeline_syncs');
        Schema::dropIfExists('micro_pipeline_records');

        parent::tearDown();
    }

    public function test_sync_uses_month_statuses_and_only_imports_area_6(): void
    {
        $result = app(MicroPipelineSyncService::class)->sync(null, $this->workbookPath, true);

        $this->assertTrue($result['changed']);
        $this->assertSame(4, $result['imported_rows']);
        $this->assertSame(1, $result['outside_scope_rows']);
        $this->assertSame(['done' => 2, 'scheduled' => 1, 'pending' => 1], $result['statuses']);

        $this->assertDatabaseHas('micro_pipeline_records', [
            'debtor_name' => 'Debitur Madiun',
            'source_key' => 'prewash',
            'branch_key' => 'madiun',
            'source_pipeline' => 'Source Terkini',
            'visit_status' => 'done',
            'visit_count' => 1,
            'planned_count' => 1,
        ]);
        $this->assertDatabaseHas('micro_pipeline_records', [
            'debtor_name' => 'Debitur Magetan',
            'visit_status' => 'scheduled',
            'planned_count' => 1,
        ]);
        $this->assertDatabaseHas('micro_pipeline_records', [
            'debtor_name' => 'Debitur Ngawi',
            'mantri_name' => null,
        ]);
        $this->assertDatabaseMissing('micro_pipeline_records', ['debtor_name' => 'Debitur Malang']);

        $unchanged = app(MicroPipelineSyncService::class)->sync(null, $this->workbookPath);
        $this->assertFalse($unchanged['changed']);
        $this->assertSame(5, $unchanged['source_rows']);
        $this->assertSame(['done' => 2, 'scheduled' => 1, 'pending' => 1], $unchanged['statuses']);
    }

    public function test_slik_hijau_sync_is_separate_and_maps_berminat_sheet(): void
    {
        app(MicroPipelineSyncService::class)->sync(null, $this->workbookPath, true);
        $result = app(MicroPipelineSyncService::class)->sync(null, $this->slikWorkbookPath, true, 'slik_hijau');

        $this->assertSame(2, $result['imported_rows']);
        $this->assertSame(1, $result['outside_scope_rows']);
        $this->assertSame('Berminat 1', $result['sheet']);
        $this->assertDatabaseHas('micro_pipeline_records', [
            'source_key' => 'slik_hijau',
            'debtor_name' => 'Nasabah Hijau Madiun',
            'branch_key' => 'madiun',
            'source_pipeline' => 'SLIK Hijau',
            'recommended_product' => 'SUPLESI/TOP UP',
            'plafond' => 25000000,
            'visit_status' => 'done',
        ]);
        $this->assertDatabaseHas('micro_pipeline_records', [
            'source_key' => 'prewash',
            'debtor_name' => 'Debitur Madiun',
        ]);

        $payload = app(LandingMicroPipelineService::class)->payload(null);
        $this->assertSame(4, $payload['summary']['total']);
        $this->assertSame(2, data_get($payload, 'slik_hijau.summary.total'));
        $this->assertSame(45000000.0, data_get($payload, 'slik_hijau.summary.potential_plafond'));

        $records = app(LandingMicroPipelineService::class)->records(null, [
            'dataset' => 'slik_hijau',
            'status' => 'open',
            'per_page' => 10,
        ]);
        $this->assertSame(2, $records['meta']['total']);
        $this->assertSame('slik_hijau', $records['data'][0]['dataset']);
    }

    public function test_landing_payload_and_nominative_filters_follow_branch_scope(): void
    {
        app(MicroPipelineSyncService::class)->sync(null, $this->workbookPath, true);
        $service = app(LandingMicroPipelineService::class);

        $area = $service->payload(null);
        $this->assertTrue($area['available']);
        $this->assertSame(4, $area['summary']['total']);
        $this->assertSame(2, $area['summary']['done']);
        $this->assertSame(50.0, $area['summary']['visit_rate']);
        $this->assertSame('area6', $area['query_scope']);

        $madiun = $service->payload(['key' => 'madiun', 'label' => 'KC Madiun']);
        $this->assertSame(1, $madiun['summary']['total']);
        $this->assertSame('madiun', $madiun['query_scope']);

        $scheduled = $service->records(null, ['status' => 'scheduled', 'per_page' => 10]);
        $this->assertSame(1, $scheduled['meta']['total']);
        $this->assertSame('Debitur Magetan', $scheduled['data'][0]['debtor_name']);
        $this->assertSame(['Feb'], $scheduled['data'][0]['planned_months']);

        $search = $service->records(null, ['search' => 'catatan ngawi', 'per_page' => 10]);
        $this->assertSame(1, $search['meta']['total']);
        $this->assertSame('Debitur Ngawi', $search['data'][0]['debtor_name']);

        $product = $service->records(null, ['product' => 'KUR KPP', 'per_page' => 10]);
        $this->assertSame(1, $product['meta']['total']);
        $this->assertSame('Debitur Ponorogo', $product['data'][0]['debtor_name']);
        $this->assertSame(20, data_get($area, 'initial_records.meta.per_page'));
    }

    private function writeWorkbook(): void
    {
        $headers = [
            'nama_debt', 'alamat', 'nomorhandphone', 'cif', 'norek_pinjaman', 'branch',
            'nama_unit', 'nama_kanca', 'kanwil', 'plafond', 'os', 'sumberpipeline',
            'pn_mantri', 'nama_mantri', 'product_rekomendasi', 'score', 'idpipeline_lama',
            'sumberpipeline_lama', 'sumberpipeline', 'Keterangan', 'Plafond Real',
            'Status Kunjungan', '', '', 'JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN',
            'JUL', 'AGT', 'SEP', 'OKT', 'NOV', 'DES',
        ];
        $rows = [
            $this->row('Debitur Madiun', 'CIF-1', '001', 'UNIT MADIUN', 'KC Madiun', 100000000, 'Source Lama', 'Legacy A', 'Source Terkini', 'Catatan Madiun', 'KUR Mikro', 'DONE', 'PLAN'),
            $this->row('Debitur Magetan', 'CIF-2', '002', 'UNIT MAGETAN', 'KC Magetan', 80000000, 'Source B', 'Legacy B', 'Source B', 'Catatan Magetan', 'Kupedes', '', 'PLAN'),
            $this->row('Debitur Ngawi', 'CIF-3', '003', 'UNIT NGAWI', 'KC Ngawi', 70000000, 'Source C', 'Legacy C', 'Source C', 'Catatan Ngawi', 'KUR Kecil'),
            $this->row('Debitur Ponorogo', 'CIF-4', '004', 'UNIT PONOROGO', 'KC Ponorogo', 90000000, 'Source D', 'Legacy D', 'Source D', 'Catatan Ponorogo', 'KUR KPP', '', '', 'DONE'),
            $this->row('Debitur Malang', 'CIF-5', '005', 'UNIT MALANG', 'KC Malang', 60000000, 'Source E', 'Legacy E', 'Source E', 'Di luar area', 'Kupedes'),
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pipeline Agustus');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        (new Xlsx($spreadsheet))->save($this->workbookPath);
        $spreadsheet->disconnectWorksheets();
    }

    private function writeSlikWorkbook(): void
    {
        $headers = [
            'region_desc', 'id', 'tgl_kunjungan', 'pn', 'nama_pegawai', 'branch', 'nama_uker',
            'mainbr_desc', 'status_kunjungan', 'alamat_kunjungan', 'nama_debitur', 'hasil_kunjungan',
            'detail_hasil_kunjungan', 'perkiraan_plafond', 'feedback_masalah', 'cifno', 'Pipeline',
            'TL Kunjungan', 'Keterangan',
        ];
        $rows = [
            ['KANWIL MALANG', 'SLIK-1', 46244, '12345', 'MANTRI A', '6339', 'UNIT MADIUN', 'KC Madiun', 'DONE', 'Alamat 1', 'Nasabah Hijau Madiun', 'NASABAH BERMINAT', 'REFERRAL', 25000000, 'Siap', 'CIF-S1', 'SUPLESI/TOP UP', '', 'Catatan 1'],
            ['KANWIL MALANG', 'SLIK-2', 46245, '22345', 'MANTRI B', '7339', 'UNIT NGAWI', 'KC Ngawi', 'DONE', 'Alamat 2', 'Nasabah Hijau Ngawi', 'NASABAH BERMINAT', 'REFERRAL', 20000000, '', 'CIF-S2', 'BARU', 'Follow up', ''],
            ['KANWIL MALANG', 'SLIK-3', 46246, '32345', 'MANTRI C', '8339', 'UNIT MALANG', 'KC Malang', 'DONE', 'Alamat 3', 'Nasabah Hijau Malang', 'NASABAH BERMINAT', 'REFERRAL', 30000000, '', 'CIF-S3', 'BARU', '', ''],
            ['KANWIL MALANG', 'SLIK-4', 46247, '42345', 'MANTRI D', '9339', 'UNIT MAGETAN', 'KC Magetan', 'OPEN', 'Alamat 4', 'Bukan Berminat', 'BELUM BERMINAT', 'REFERRAL', 10000000, '', 'CIF-S4', 'BARU', '', ''],
        ];

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('BO')->fromArray([['Tidak digunakan']], null, 'A1');
        $spreadsheet->createSheet()->setTitle('Berminat 1')->fromArray($headers, null, 'A1');
        $spreadsheet->getSheetByName('Berminat 1')->fromArray($rows, null, 'A2');
        (new Xlsx($spreadsheet))->save($this->slikWorkbookPath);
        $spreadsheet->disconnectWorksheets();
    }

    /** @return array<int, mixed> */
    private function row(
        string $debtor,
        string $cif,
        string $unitCode,
        string $unit,
        string $branch,
        float $plafond,
        string $source,
        string $legacySource,
        string $latestSource,
        string $description,
        string $product,
        string $jan = '',
        string $feb = '',
        string $mar = ''
    ): array {
        $row = array_fill(0, 36, '');
        $row[0] = $debtor;
        $row[3] = $cif;
        $row[5] = $unitCode;
        $row[6] = $unit;
        $row[7] = $branch;
        $row[9] = $plafond;
        $row[10] = $plafond * .75;
        $row[11] = $source;
        $row[12] = '12345';
        $row[13] = $debtor === 'Debitur Ngawi' ? 'NULL' : 'MANTRI TEST';
        $row[14] = $product;
        $row[15] = 90;
        $row[16] = 'PIPE-'.$cif;
        $row[17] = $legacySource;
        $row[18] = $latestSource;
        $row[19] = $description;
        $row[20] = 10000000;
        $row[21] = $jan === 'DONE' ? 1 : 0;
        $row[24] = $jan;
        $row[25] = $feb;
        $row[26] = $mar;

        return $row;
    }
}
